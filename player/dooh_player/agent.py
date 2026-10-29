"""Player agent main loop.

Talks to the device endpoint over mutual TLS (client cert + key on disk, private CA bundle),
keeps playing the last good manifest from cache when the network is down.
"""

from __future__ import annotations

import argparse
import base64
import logging
import os
import time
from pathlib import Path

import requests

from .cache import HashMismatch, MediaCache
from .manifest import Manifest, ManifestError, verify
from .state import State

log = logging.getLogger("dooh_player")


class Agent:
    def __init__(self, *, api: str, screen_id: str, data_dir: Path, cert: Path, key: Path, ca: Path, public_key: bytes):
        self.api = api.rstrip("/")
        self.screen_id = screen_id
        self.cache = MediaCache(data_dir / "media")
        self.state = State(data_dir / "state.json")
        self.public_key = public_key
        self.http = requests.Session()
        self.http.cert = (str(cert), str(key))
        self.http.verify = str(ca)

    def sync(self) -> Manifest | None:
        """Fetch, verify and cache the latest manifest. Returns it if newly accepted."""
        st = self.state.load()
        res = self.http.get(f"{self.api}/device/v1/manifest", timeout=15)
        res.raise_for_status()
        envelope = res.json()["data"]
        if envelope is None:
            return None
        try:
            manifest = verify(envelope, self.public_key, screen_id=self.screen_id,
                              last_version=st["last_version"], now=int(time.time()))
        except ManifestError as e:
            log.warning("rejected manifest: %s", e)
            return None

        for item in manifest.items:
            if not self.cache.has(item.sha256):
                with self.http.get(item.url, stream=True, timeout=60) as r:
                    r.raise_for_status()
                    self.cache.store(item.sha256, r.iter_content(64 * 1024))

        self.state.save({"last_version": manifest.version, "manifest": envelope})
        self.cache.prune({i.sha256 for i in manifest.items})
        log.info("accepted manifest v%d with %d items", manifest.version, len(manifest.items))
        return manifest

    def run(self, interval: int = 60) -> None:
        while True:
            try:
                self.sync()
            except (requests.RequestException, HashMismatch, OSError) as e:
                log.warning("sync failed, playing from cache: %s", e)
            time.sleep(interval)


def main() -> None:
    p = argparse.ArgumentParser(description="Rentall Screen Ads player agent")
    p.add_argument("--api", default=os.environ.get("DOOH_API", "https://localhost:8443"))
    p.add_argument("--screen-id", default=os.environ.get("DOOH_SCREEN_ID"), required="DOOH_SCREEN_ID" not in os.environ)
    p.add_argument("--data-dir", type=Path, default=Path(os.environ.get("DOOH_DATA_DIR", "/var/lib/dooh")))
    p.add_argument("--cert", type=Path, default=Path(os.environ.get("DOOH_CERT", "/etc/dooh/device.crt")))
    p.add_argument("--key", type=Path, default=Path(os.environ.get("DOOH_KEY", "/etc/dooh/device.key")))
    p.add_argument("--ca", type=Path, default=Path(os.environ.get("DOOH_CA", "/etc/dooh/ca.crt")))
    p.add_argument("--signing-pubkey", default=os.environ.get("DOOH_SIGNING_PUBKEY"),
                   required="DOOH_SIGNING_PUBKEY" not in os.environ, help="base64 Ed25519 public key")
    args = p.parse_args()

    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
    args.data_dir.mkdir(parents=True, exist_ok=True)
    Agent(api=args.api, screen_id=args.screen_id, data_dir=args.data_dir, cert=args.cert, key=args.key,
          ca=args.ca, public_key=base64.b64decode(args.signing_pubkey)).run()


if __name__ == "__main__":
    main()
