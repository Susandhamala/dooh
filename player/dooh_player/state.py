"""Small persisted state: the last accepted manifest version, so replays survive reboots."""

from __future__ import annotations

import json
import os
from pathlib import Path


class State:
    def __init__(self, path: Path):
        self.path = path

    def load(self) -> dict:
        try:
            return json.loads(self.path.read_text())
        except FileNotFoundError:
            return {"last_version": 0, "manifest": None}

    def save(self, data: dict) -> None:
        tmp = self.path.with_suffix(".tmp")
        tmp.write_text(json.dumps(data))
        os.replace(tmp, self.path)
