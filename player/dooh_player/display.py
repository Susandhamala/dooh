"""Putting a file on the screen.

MpvDisplay drives one long-running mpv over its JSON IPC socket, so switching ads is instant and
there is no flash of the console between them. On Raspberry Pi OS Lite mpv draws straight to
the HDMI output (DRM/KMS), with no desktop. LogDisplay is for emulated screens in Docker.
"""

from __future__ import annotations

import json
import logging
import os
import shlex
import socket
import subprocess
import time
from pathlib import Path
from typing import Protocol

log = logging.getLogger("dooh_player")


class Display(Protocol):
    def show(self, path: Path, label: str) -> None: ...
    def blank(self) -> None: ...
    def close(self) -> None: ...


class LogDisplay:
    def __init__(self) -> None:
        self.current: str | None = None

    def show(self, path: Path, label: str) -> None:
        log.info("showing %s", label)
        self.current = label

    def blank(self) -> None:
        if self.current is not None:
            log.info("blank")
        self.current = None

    def close(self) -> None:
        pass


class MpvDisplay:
    ARGS = [
        "--idle=yes", "--force-window=yes", "--fullscreen", "--keep-open=yes",
        "--image-display-duration=inf", "--no-audio", "--hwdec=auto-safe",
        "--no-osc", "--no-osd-bar", "--osd-level=0", "--cursor-autohide=always",
        "--no-input-default-bindings", "--input-vo-keyboard=no", "--really-quiet",
    ]

    def __init__(self, socket_path: Path, extra_args: str = "") -> None:
        self.socket_path = socket_path
        self.extra_args = shlex.split(extra_args)
        self.proc: subprocess.Popen | None = None
        self.showing: Path | None = None

    def show(self, path: Path, label: str) -> None:
        self._command("loadfile", str(path), "replace")
        self.showing = path
        log.info("showing %s", label)

    def blank(self) -> None:
        if self.showing is not None:
            self._command("stop")
        self.showing = None

    def close(self) -> None:
        if self.proc and self.proc.poll() is None:
            self.proc.terminate()
            self.proc.wait(timeout=5)

    def _start(self) -> None:
        self.socket_path.unlink(missing_ok=True)
        self.proc = subprocess.Popen(
            ["mpv", *self.ARGS, f"--input-ipc-server={self.socket_path}", *self.extra_args],
            stdin=subprocess.DEVNULL,
        )
        for _ in range(100):
            if self.socket_path.exists():
                return
            if self.proc.poll() is not None:
                break
            time.sleep(0.1)
        raise RuntimeError(f"mpv did not start (exit code {self.proc.poll()}); check `journalctl -u dooh-player`")

    def _command(self, *args: str) -> None:
        if self.proc is None or self.proc.poll() is not None:
            if self.proc is not None:
                log.warning("mpv exited with %s, restarting", self.proc.returncode)
            self._start()
            self.showing = None
        # One short connection per command, so unread mpv events never pile up.
        with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as s:
            s.settimeout(5)
            s.connect(os.fspath(self.socket_path))
            s.sendall(json.dumps({"command": list(args)}).encode() + b"\n")
            s.recv(4096)
