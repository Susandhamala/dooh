import base64
import hashlib
import json

import pytest
from nacl.signing import SigningKey

from dooh_player.agent import Agent
from dooh_player.config import Config

NOW = 1_794_284_100  # 10:00 in Kathmandu, start of a loop
MEDIA = b"ad image"
SHA = hashlib.sha256(MEDIA).hexdigest()


class Response:
    def __init__(self, status=200, body=None, content=b""):
        self.status_code, self.body, self.content = status, body, content

    def json(self):
        return self.body

    def raise_for_status(self):
        if self.status_code >= 400:
            raise RuntimeError(self.status_code)

    def iter_content(self, size):
        yield self.content

    def __enter__(self):
        return self

    def __exit__(self, *a):
        pass


class Server:
    def __init__(self, key: SigningKey):
        self.key, self.envelope, self.status, self.gets = key, None, 200, []
        self.cert = self.verify = None
        self.headers = {}

    def publish(self, version: int, items: list[dict]):
        raw = json.dumps({"screen_id": "scr-1", "version": version, "valid_from": NOW - 300, "expires_at": NOW + 86400,
                          "tz_offset_s": 20700, "loop_seconds": 120, "spot_seconds": 10, "items": items}).encode()
        self.envelope = {"payload": base64.b64encode(raw).decode(), "signature": base64.b64encode(self.key.sign(raw).signature).decode()}

    def get(self, url, **kw):
        self.gets.append(url)
        if url.endswith("/device/v1/manifest"):
            return Response(self.status, {"data": self.envelope})
        return Response(content=MEDIA)


class Screen:
    def __init__(self):
        self.shown = []

    def show(self, path, label):
        self.shown.append(path.read_bytes())

    def blank(self):
        self.shown.append(None)

    def close(self):
        pass


AD = {"creative_id": "c1", "url": "/device/v1/media/c1", "sha256": SHA, "duration_s": 10, "slot_index": 1, "windows": [[NOW, NOW + 3600]]}


@pytest.fixture
def setup(tmp_path):
    key = SigningKey.generate()
    server, screen = Server(key), Screen()
    config = Config(api="https://pi.lab:8443", screen_id="scr-1", screen_name="S", device_id="d",
                    signing_public_key=base64.b64encode(bytes(key.verify_key)).decode())
    clock = [NOW]

    def make():
        return Agent(config=config, config_dir=tmp_path / "etc", data_dir=tmp_path, display=screen, http=server, clock=lambda: clock[0])

    return server, screen, make, clock


def test_downloads_from_the_device_api_and_plays_in_the_booked_slot(setup):
    server, screen, make, clock = setup
    agent = make()
    assert server.headers["X-Player-Version"]
    server.publish(10, [AD])

    assert agent.sync().version == 10
    assert server.gets[-1] == "https://pi.lab:8443/device/v1/media/c1"
    assert agent.tick() == 10  # slot 0: nothing booked
    clock[0] = NOW + 12
    assert agent.tick() == 8
    assert screen.shown == [None, MEDIA]


def test_an_unchanged_manifest_is_not_rejected_as_a_replay(setup, caplog):
    server, _, make, _ = setup
    agent = make()
    server.publish(10, [AD])
    agent.sync()
    assert agent.sync() is None
    assert "rejected" not in caplog.text


def test_keeps_playing_the_saved_manifest_after_a_reboot_offline(setup):
    server, screen, make, clock = setup
    server.publish(10, [AD])
    make().sync()

    server.status = 503
    agent = make()
    agent.restore()
    clock[0] = NOW + 15
    agent.tick()
    assert screen.shown == [MEDIA]


def test_goes_blank_when_the_manifest_expires(setup):
    server, screen, make, clock = setup
    agent = make()
    server.publish(10, [AD])
    agent.sync()
    clock[0] = NOW + 86400
    agent.tick()
    assert screen.shown == [None]


def test_stops_playing_when_disconnected_and_says_why(setup, caplog):
    server, screen, make, clock = setup
    agent = make()
    server.publish(10, [AD])
    agent.sync()

    server.status = 403
    assert agent.sync() is None
    assert "enrol it again" in caplog.text
    clock[0] = NOW + 15
    agent.tick()
    assert screen.shown == [None]

    rebooted = make()
    rebooted.restore()
    assert rebooted.current() is None
    assert rebooted.state.load()["last_version"] == 10


def test_a_longer_ad_starts_once_and_runs_through_its_slots(setup):
    server, screen, make, clock = setup
    agent = make()
    server.publish(10, [AD | {"duration_s": 40, "slot_index": 2, "slot_count": 4}])
    agent.sync()
    clock[0] = NOW + 20
    assert agent.tick() == 40  # slots 2–5, then the next change
    clock[0] = NOW + 60
    agent.tick()
    assert screen.shown == [MEDIA, None]
