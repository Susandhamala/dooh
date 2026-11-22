"""What a device learns when it enrols, kept in its config directory (default /etc/dooh):

    player.json   API address, screen and device ids, manifest public key
    device.key    private key, made on this device (mode 600, never sent anywhere)
    device.crt    client certificate the API signed at enrolment
    ca.crt        device CA: verifies the server, and signed device.crt
"""

from __future__ import annotations

import json
import os
from dataclasses import asdict, dataclass
from pathlib import Path


class NotEnrolled(Exception):
    pass


@dataclass(frozen=True)
class Config:
    api: str
    screen_id: str
    screen_name: str
    device_id: str
    signing_public_key: str  # base64 Ed25519

    @staticmethod
    def load(config_dir: Path) -> Config:
        try:
            data = json.loads((config_dir / "player.json").read_text())
        except FileNotFoundError as e:
            raise NotEnrolled(f"no {config_dir / 'player.json'}: run `dooh-player enrol` first") from e
        return Config(**{k: data[k] for k in Config.__dataclass_fields__})

    def save(self, config_dir: Path) -> None:
        write_file(config_dir / "player.json", json.dumps(asdict(self), indent=2).encode())


def cert_path(config_dir: Path) -> Path:
    return config_dir / "device.crt"


def key_path(config_dir: Path) -> Path:
    return config_dir / "device.key"


def ca_path(config_dir: Path) -> Path:
    return config_dir / "ca.crt"


def write_file(path: Path, data: bytes, mode: int = 0o644) -> None:
    """Write via a temp file and rename, so a power cut never leaves half a key or config."""
    tmp = path.with_name(f".{path.name}.tmp")
    fd = os.open(tmp, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, mode)
    with os.fdopen(fd, "wb") as f:
        f.write(data)
        f.flush()
        os.fsync(f.fileno())
    os.replace(tmp, path)
