import hashlib

import pytest

from dooh_player.cache import HashMismatch, MediaCache


def test_stores_file_when_hash_matches(tmp_path):
    cache = MediaCache(tmp_path)
    data = b"video bytes"
    sha = hashlib.sha256(data).hexdigest()
    path = cache.store(sha, [data[:5], data[5:]])
    assert path.read_bytes() == data and cache.has(sha)


def test_discards_file_when_hash_differs(tmp_path):
    cache = MediaCache(tmp_path)
    with pytest.raises(HashMismatch):
        cache.store("0" * 64, [b"tampered"])
    assert list(tmp_path.iterdir()) == []
