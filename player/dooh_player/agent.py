"""Player agent.

Two loops: a background thread fetches and verifies the signed manifest every minute over mutual
TLS and downloads (hash-checked) media; the main thread puts the right ad on screen at each slot
boundary. With the network down it keeps playing the last good manifest until that expires,
including after a reboot.

    dooh-player enrol --api https://192.168.1.50:8443 --ca ca.crt --code ABCD-EFGH
    dooh-player run
"""

from __future__ import annotations

import argparse
import base64
import logging
import os
import sys
import threading
import time
from importlib.metadata import PackageNotFoundError, version
from pathlib import Path

import requests

from . import schedule
from .cache import HashMismatch, MediaCache
from .config import Config, NotEnrolled, ca_path, cert_path, key_path
from .display import Display, LogDisplay, MpvDisplay
from .enrol import EnrolError, enrol
from .manifest import Manifest, ManifestError, verify
from .state import State

log = logging.getLogger("dooh_player")

try:
    VERSION = version("dooh-player")
except PackageNotFoundError:
    VERSION = "dev"


class Agent:
    def __init__(self, *, config: Config, config_dir: Path, data_dir: Path, display: Display,
                 http: requests.Session | None = None, clock=time.time):
        self.config = config
        self.public_key = base64.b64decode(config.signing_public_key)
        self.cache = MediaCache(data_dir / "media")
        self.state = State(data_dir / "state.json")
        self.display = display
        self.clock = clock
        self.http = http or requests.Session()
        self.http.cert = (str(cert_path(config_dir)), str(key_path(config_dir)))
        self.http.verify = str(ca_path(config_dir))
        self.http.headers["X-Player-Version"] = VERSION
        self.manifest: Manifest | None = None
        self._lock = threading.Lock()

    def current(self) -> Manifest | None:
        with self._lock:
            return self.manifest

    def restore(self) -> None:
        """After a reboot: play the manifest accepted last time, if it is still valid and cached."""
        st = self.state.load()
        if not st["manifest"]:
            return
        try:
            m = verify(st["manifest"], self.public_key, screen_id=self.config.screen_id,
                       last_version=st["last_version"] - 1, now=int(self.clock()))
        except ManifestError as e:
            log.info("saved manifest not usable: %s", e)
            return
        if all(self.cache.has(i.sha256) for i in m.items):
            with self._lock:
                self.manifest = m
            log.info("playing saved manifest v%d until the server is reachable", m.version)

    def sync(self) -> Manifest | None:
        """Fetch, verify and cache the latest manifest. Returns it if newly accepted."""
        st = self.state.load()
        res = self.http.get(f"{self.config.api}/device/v1/manifest", timeout=15)
        if res.status_code in (401, 403):
            # Disconnected by the owner: stop showing ads. last_version stays, so old manifests can't be replayed.
            log.error("server refused this device (%s): it was disconnected or never enrolled; "
                      "enrol it again with a new code", res.status_code)
            with self._lock:
                self.manifest = None
            self.state.save({"last_version": st["last_version"], "manifest": None})
            return None
        res.raise_for_status()
        envelope = res.json()["data"]
        if envelope is None or envelope == st["manifest"]:
            return None
        try:
            manifest = verify(envelope, self.public_key, screen_id=self.config.screen_id,
                              last_version=st["last_version"], now=int(self.clock()))
        except ManifestError as e:
            log.warning("rejected manifest: %s", e)
            return None

        for item in manifest.items:
            if not self.cache.has(item.sha256):
                url = item.url if item.url.startswith("https://") else self.config.api + item.url
                with self.http.get(url, stream=True, timeout=60) as r:
                    r.raise_for_status()
                    self.cache.store(item.sha256, r.iter_content(64 * 1024))

        self.state.save({"last_version": manifest.version, "manifest": envelope})
        with self._lock:
            self.manifest = manifest
        self.cache.prune({i.sha256 for i in manifest.items})
        log.info("accepted manifest v%d with %d items", manifest.version, len(manifest.items))
        return manifest

    def sync_forever(self, interval: int) -> None:
        while True:
            try:
                self.sync()
            except (requests.RequestException, HashMismatch, OSError, ValueError) as e:
                log.warning("sync failed, playing from cache: %s", e)
            time.sleep(interval)

    def tick(self) -> float:
        """Show what belongs on screen now; return seconds until the next change."""
        m = self.current()
        now = self.clock()
        if m is None or not m.valid_from <= now < m.expires_at:
            self.display.blank()
            return 5.0
        slot, wait = schedule.slot_at(m, now)
        item = schedule.item_for(m, slot, now) if slot is not None else None
        if item and self.cache.has(item.sha256):
            # A longer ad spans several slots: start it once and let it run through its last slot.
            last = item.slot_index + item.slot_count - 1
            self.display.show(self.cache.path_for(item.sha256), f"{item.creative_id} (slot {item.slot_index}{f'–{last}' if last > item.slot_index else ''})")
            wait = schedule.seconds_left(m, item, slot, wait)
        else:
            self.display.blank()
        return wait

    def run(self, interval: int = 60) -> None:
        self.restore()
        threading.Thread(target=self.sync_forever, args=(interval,), daemon=True, name="sync").start()
        try:
            while True:
                # A hair past the boundary, so the next tick lands inside the new slot.
                time.sleep(self.tick() + 0.01)
        finally:
            self.display.close()


def main(argv: list[str] | None = None) -> None:
    common = argparse.ArgumentParser(add_help=False)
    common.add_argument("--config-dir", type=Path, default=Path(os.environ.get("DOOH_CONFIG_DIR", "/etc/dooh")))
    p = argparse.ArgumentParser(prog="dooh-player", description="Rentall Screen Ads player agent")
    p.add_argument("--version", action="version", version=VERSION)
    sub = p.add_subparsers(dest="command", required=True)

    e = sub.add_parser("enrol", parents=[common], help="connect this device to a screen with a code from the portal")
    e.add_argument("--api", required=True, help="device API, e.g. https://192.168.1.50:8443")
    e.add_argument("--ca", type=Path, required=True, help="the server's ca.crt (infra/ca/out/ca.crt)")
    e.add_argument("--code", required=True, help="one-time code from My screens → Connect device")

    r = sub.add_parser("run", parents=[common], help="play the screen's ads")
    r.add_argument("--data-dir", type=Path, default=Path(os.environ.get("DOOH_DATA_DIR", "/var/lib/dooh")))
    r.add_argument("--display", choices=["mpv", "log"], default=os.environ.get("DOOH_DISPLAY", "mpv"))
    r.add_argument("--interval", type=int, default=60, help="seconds between manifest checks")
    args = p.parse_args(argv)

    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
    if args.command == "enrol":
        try:
            config = enrol(args.api, args.code, args.ca, args.config_dir)
        except (EnrolError, OSError) as err:
            sys.exit(f"enrolment failed: {err}")
        print(f"Enrolled as device {config.device_id} for screen “{config.screen_name}”.")
        return

    try:
        config = Config.load(args.config_dir)
    except NotEnrolled as err:
        sys.exit(str(err))
    args.data_dir.mkdir(parents=True, exist_ok=True)
    display: Display = LogDisplay() if args.display == "log" else MpvDisplay(
        Path(os.environ.get("RUNTIME_DIRECTORY", args.data_dir)) / "mpv.sock", os.environ.get("DOOH_MPV_ARGS", ""))
    log.info("player %s for screen “%s” (%s), API %s", VERSION, config.screen_name, config.screen_id, config.api)
    Agent(config=config, config_dir=args.config_dir, data_dir=args.data_dir, display=display).run(args.interval)


if __name__ == "__main__":
    main()
