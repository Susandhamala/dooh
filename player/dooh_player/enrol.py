"""Enrolment: make a key pair here, send only a CSR with the owner's one-time code, keep the certificate.

The connection is verified against the device CA given on the command line (copied from the
server), so the code can't be handed to an impostor server.
"""

from __future__ import annotations

import socket
from pathlib import Path

import requests
from cryptography import x509
from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey
from cryptography.x509.oid import NameOID

from .config import Config, ca_path, cert_path, key_path, write_file


class EnrolError(Exception):
    pass


def enrol(api: str, code: str, ca: Path, config_dir: Path, http: requests.Session | None = None) -> Config:
    api = api.rstrip("/")
    if not api.startswith("https://"):
        raise EnrolError("the API address must start with https://")
    ca_pem = ca.read_bytes()

    key = Ed25519PrivateKey.generate()
    name = (socket.gethostname() or "dooh-player")[:64]
    csr = x509.CertificateSigningRequestBuilder().subject_name(
        x509.Name([x509.NameAttribute(NameOID.COMMON_NAME, name)])
    ).sign(key, None)

    http = http or requests.Session()
    try:
        res = http.post(f"{api}/device/v1/enrol", timeout=30, verify=str(ca), json={
            "code": code,
            "csr": csr.public_bytes(serialization.Encoding.PEM).decode(),
        })
    except requests.exceptions.SSLError as e:
        raise EnrolError(f"the server's certificate doesn't match {ca} (or doesn't list this address): {e}") from e
    except requests.RequestException as e:
        raise EnrolError(f"could not reach {api}: {e}") from e
    if res.status_code != 201:
        try:
            body = res.json()
            message = body.get("message") or res.reason
            details = [m for msgs in (body.get("errors") or {}).values() for m in msgs]
        except ValueError:
            message, details = res.reason, []
        raise EnrolError(" ".join([f"{res.status_code}: {message}", *details]))

    data = res.json()["data"]
    cert = x509.load_pem_x509_certificate(data["certificate"].encode())
    raw = serialization.Encoding.Raw, serialization.PublicFormat.Raw
    if cert.public_key().public_bytes(*raw) != key.public_key().public_bytes(*raw):
        raise EnrolError("the server returned a certificate for a different key")

    config_dir.mkdir(parents=True, exist_ok=True)
    write_file(key_path(config_dir), key.private_bytes(
        serialization.Encoding.PEM, serialization.PrivateFormat.PKCS8, serialization.NoEncryption()), mode=0o600)
    write_file(cert_path(config_dir), data["certificate"].encode())
    write_file(ca_path(config_dir), ca_pem)
    config = Config(api=api, screen_id=data["screen_id"], screen_name=data["screen_name"],
                    device_id=data["device_id"], signing_public_key=data["signing_public_key"])
    config.save(config_dir)
    return config
