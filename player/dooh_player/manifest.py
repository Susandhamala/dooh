"""Signed playlist manifests.

Wire format (what the API sends):

    {"payload": "<base64 of UTF-8 JSON bytes>", "signature": "<base64 Ed25519 signature of those bytes>"}

The signature covers the exact payload bytes, so neither side has to canonicalise JSON.
The payload JSON looks like:

    {"screen_id": "...", "version": 7, "valid_from": 1730000000, "expires_at": 1730086400,
     "items": [{"creative_id": "...", "url": "...", "sha256": "<hex>", "duration_s": 10}]}

Times are Unix seconds. A device accepts a manifest only if the signature verifies, it is
for this screen, its version is strictly newer than the last accepted one, and now falls
inside [valid_from, expires_at].
"""

from __future__ import annotations

import base64
import binascii
import json
import re
from dataclasses import dataclass

from nacl.exceptions import BadSignatureError
from nacl.signing import VerifyKey

_SHA256_HEX = re.compile(r"^[0-9a-f]{64}$")


class ManifestError(Exception):
    """The manifest must not be played."""


@dataclass(frozen=True)
class Item:
    creative_id: str
    url: str
    sha256: str
    duration_s: int


@dataclass(frozen=True)
class Manifest:
    screen_id: str
    version: int
    valid_from: int
    expires_at: int
    items: tuple[Item, ...]


def verify(envelope: dict, public_key: bytes, *, screen_id: str, last_version: int, now: int) -> Manifest:
    """Verify a manifest envelope and return the parsed manifest, or raise ManifestError."""
    try:
        payload = base64.b64decode(envelope["payload"], validate=True)
        signature = base64.b64decode(envelope["signature"], validate=True)
    except (KeyError, TypeError, binascii.Error) as e:
        raise ManifestError("malformed envelope") from e

    try:
        VerifyKey(public_key).verify(payload, signature)
    except (BadSignatureError, ValueError) as e:
        raise ManifestError("bad signature") from e

    # Only parse after the signature checks out.
    try:
        data = json.loads(payload)
        manifest = Manifest(
            screen_id=str(data["screen_id"]),
            version=_int(data["version"]),
            valid_from=_int(data["valid_from"]),
            expires_at=_int(data["expires_at"]),
            items=tuple(_item(i) for i in data["items"]),
        )
    except (KeyError, TypeError, ValueError) as e:
        raise ManifestError("malformed payload") from e

    if manifest.screen_id != screen_id:
        raise ManifestError("manifest is for another screen")
    if manifest.version <= last_version:
        raise ManifestError(f"stale or replayed version {manifest.version} (last {last_version})")
    if not manifest.valid_from <= now < manifest.expires_at:
        raise ManifestError("outside validity window")
    return manifest


def _int(value: object) -> int:
    if not isinstance(value, int) or isinstance(value, bool):
        raise ValueError("expected integer")
    return value


def _item(data: dict) -> Item:
    sha = str(data["sha256"])
    if not _SHA256_HEX.match(sha):
        raise ValueError("bad sha256")
    url = str(data["url"])
    if not url.startswith("https://"):
        raise ValueError("item url must be https")
    duration = _int(data["duration_s"])
    if duration <= 0:
        raise ValueError("bad duration")
    return Item(creative_id=str(data["creative_id"]), url=url, sha256=sha, duration_s=duration)
