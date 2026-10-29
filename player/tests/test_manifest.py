import base64
import json

import pytest
from nacl.signing import SigningKey

from dooh_player.manifest import ManifestError, verify

NOW = 1_800_000_000
SHA = "a" * 64


def make(key: SigningKey, **overrides) -> dict:
    payload = {
        "screen_id": "screen-1",
        "version": 5,
        "valid_from": NOW - 60,
        "expires_at": NOW + 3600,
        "items": [{"creative_id": "c1", "url": "https://ads.example/m/c1.mp4", "sha256": SHA, "duration_s": 10}],
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


@pytest.mark.parametrize("item", [
    {"creative_id": "c", "url": "http://plain.example/x", "sha256": SHA, "duration_s": 10},
    {"creative_id": "c", "url": "https://x.example/x", "sha256": "nothex", "duration_s": 10},
    {"creative_id": "c", "url": "https://x.example/x", "sha256": SHA, "duration_s": 0},
])
def test_rejects_bad_items(key, item):
    with pytest.raises(ManifestError, match="malformed payload"):
        check(make(key, items=[item]), key)


def test_rejects_garbage_envelope(key):
    with pytest.raises(ManifestError, match="malformed envelope"):
        check({"payload": "!!", "signature": ""}, key)
