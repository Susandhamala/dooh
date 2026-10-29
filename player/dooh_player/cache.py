"""Content-addressed media cache. Files are stored by SHA-256 and only after their hash matches."""

from __future__ import annotations

import hashlib
import os
import tempfile
from pathlib import Path
from typing import Iterable


class HashMismatch(Exception):
    pass


class MediaCache:
    def __init__(self, root: Path):
        self.root = root
        self.root.mkdir(parents=True, exist_ok=True)

    def path_for(self, sha256: str) -> Path:
        return self.root / sha256

    def has(self, sha256: str) -> bool:
        return self.path_for(sha256).is_file()

    def store(self, sha256: str, chunks: Iterable[bytes]) -> Path:
        """Write chunks to a temp file, check the hash, then atomically move into place."""
        digest = hashlib.sha256()
        fd, tmp = tempfile.mkstemp(dir=self.root, prefix=".part-")
        try:
            with os.fdopen(fd, "wb") as f:
                for chunk in chunks:
                    digest.update(chunk)
                    f.write(chunk)
            if digest.hexdigest() != sha256:
                raise HashMismatch(f"expected {sha256}, got {digest.hexdigest()}")
            final = self.path_for(sha256)
            os.replace(tmp, final)
            return final
        except BaseException:
            Path(tmp).unlink(missing_ok=True)
            raise

    def prune(self, keep: set[str]) -> None:
        for p in self.root.iterdir():
            if p.is_file() and p.name not in keep:
                p.unlink()
