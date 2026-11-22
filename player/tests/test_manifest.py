import base64
import json

import pytest
from nacl.signing import SigningKey

from dooh_player.manifest import ManifestError, verify

NOW = 1_800_000_000
SHA = "a" * 64


def item(**overrides) -> dict:
    return {"creative_id": "c1", "url": "/device/v1/media/c1", "sha256": SHA, "duration_s": 10,
            "slot_index": 0, "windows": [[NOW, NOW + 3600]]} | overrides


def make(key: SigningKey, **overrides) -> dict:
    payload = {
        "screen_id": "screen-1",
        "version": 5,
        "valid_from": NOW - 60,
        "expires_at": NOW + 3600,
        "tz_offset_s": 20700,
        "loop_seconds": 120,
        "spot_seconds": 10,
        "items": [item()],
    } | overrides
    raw = json.dumps(payload).encode()
    return {"payload": base64.b64encode(raw).decode(), "signature": base64.b64encode(key.sign(raw).signature).decode()}


@pytest.fixture
def key():
    return SigningKey.generate()


def check(env, key, last_version=4):
    return verify(env, bytes(key.verify_key), screen_id="screen-1", last_version=last_version, now=NOW)


def test_accepts_valid_manifest(key):
    m = check(make(key), key)
    assert m.version == 5 and m.items[0].sha256 == SHA


def test_rejects_signature_from_other_key(key):
    with pytest.raises(ManifestError, match="bad signature"):
        check(make(SigningKey.generate()), key)


def test_rejects_tampered_payload(key):
    env = make(key)
    data = json.loads(base64.b64decode(env["payload"]))
    data["items"][0]["url"] = "https://evil.example/x.mp4"
    env["payload"] = base64.b64encode(json.dumps(data).encode()).decode()
    with pytest.raises(ManifestError, match="bad signature"):
        check(env, key)


@pytest.mark.parametrize("last", [5, 6])
def test_rejects_replayed_or_stale_version(key, last):
    with pytest.raises(ManifestError, match="stale or replayed"):
        check(make(key), key, last_version=last)


def test_rejects_expired(key):
    with pytest.raises(ManifestError, match="validity"):
        check(make(key, expires_at=NOW), key)


def test_rejects_not_yet_valid(key):
    with pytest.raises(ManifestError, match="validity"):
        check(make(key, valid_from=NOW + 1), key)


def test_rejects_manifest_for_other_screen(key):
    with pytest.raises(ManifestError, match="another screen"):
        check(make(key, screen_id="screen-2"), key)


@pytest.mark.parametrize("bad", [
    {"url": "http://plain.example/x"},
    {"url": "//evil.example/x"},
    {"sha256": "nothex"},
    {"duration_s": 0},
    {"slot_index": -1},
    {"slot_count": 0},
    {"slot_index": 10, "slot_count": 3},  # runs past the 12-slot loop
    {"windows": [[NOW, "later"]]},
])
def test_rejects_bad_items(key, bad):
    with pytest.raises(ManifestError, match="malformed payload"):
        check(make(key, items=[item(**bad)]), key)


def test_rejects_bad_loop(key):
    with pytest.raises(ManifestError, match="malformed payload"):
        check(make(key, spot_seconds=0), key)


def test_accepts_https_urls_and_reads_the_schedule(key):
    m = check(make(key, items=[item(url="https://cdn.example/c1.webp", slot_index=3, windows=[[NOW - 10, NOW + 10]])]), key)
    assert m.loop_seconds == 120 and m.items[0].slot_index == 3
    assert m.items[0].plays_at(NOW) and not m.items[0].plays_at(NOW + 10)


def test_rejects_garbage_envelope(key):
    with pytest.raises(ManifestError, match="malformed envelope"):
        check({"payload": "!!", "signature": ""}, key)


def test_reads_slot_count_and_defaults_it_to_one(key):
    m = check(make(key, items=[item(slot_index=2, slot_count=4), item(creative_id="c2", slot_index=8)]), key)
    assert (m.items[0].slot_count, m.items[1].slot_count) == (4, 1)
    assert m.items[0].covers(5) and not m.items[0].covers(6)
