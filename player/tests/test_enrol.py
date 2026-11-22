import datetime as dt
import json
import os
import stat

import pytest
import requests
from cryptography import x509
from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric.ed25519 import Ed25519PrivateKey
from cryptography.x509.oid import NameOID

from dooh_player.config import Config
from dooh_player.enrol import EnrolError, enrol


class FakeResponse:
    def __init__(self, status: int, body: dict):
        self.status_code, self._body, self.reason = status, body, "reason"

    def json(self):
        return self._body


class FakeServer:
    """Signs whatever CSR it gets with its own CA, like the API does."""

    def __init__(self, status=201, wrong_key=False):
        self.status, self.wrong_key, self.sent = status, wrong_key, None
        self.ca_key = Ed25519PrivateKey.generate()

    def post(self, url, json=None, **kw):
        self.sent = {"url": url, "json": json, **kw}
        if self.status != 201:
            return FakeResponse(self.status, {"message": "That code is wrong or has expired.", "errors": {"code": ["Invalid or expired code."]}})
        csr = x509.load_pem_x509_csr(json["csr"].encode())
        pub = Ed25519PrivateKey.generate().public_key() if self.wrong_key else csr.public_key()
        now = dt.datetime.now(dt.timezone.utc)
        cert = (x509.CertificateBuilder().subject_name(csr.subject)
                .issuer_name(x509.Name([x509.NameAttribute(NameOID.COMMON_NAME, "Test CA")]))
                .public_key(pub).serial_number(1).not_valid_before(now).not_valid_after(now + dt.timedelta(days=1))
                .sign(self.ca_key, None))
        return FakeResponse(201, {"data": {
            "device_id": "dev-1", "screen_id": "scr-1", "screen_name": "New Road",
            "certificate": cert.public_bytes(serialization.Encoding.PEM).decode(),
            "ca_certificate": "CA", "signing_public_key": "cHVi",
        }})


@pytest.fixture
def ca(tmp_path):
    path = tmp_path / "given-ca.crt"
    path.write_text("-----BEGIN CERTIFICATE-----\n...\n")
    return path


def test_sends_only_a_csr_and_keeps_the_private_key_here(tmp_path, ca):
    server, conf = FakeServer(), tmp_path / "etc"
    config = enrol("https://192.168.1.50:8443/", "abcd-efgh", ca, conf, http=server)

    assert server.sent["url"] == "https://192.168.1.50:8443/device/v1/enrol"
    assert server.sent["verify"] == str(ca)
    assert set(server.sent["json"]) == {"code", "csr"} and "PRIVATE" not in json.dumps(server.sent["json"])
    assert config == Config(api="https://192.168.1.50:8443", screen_id="scr-1", screen_name="New Road",
                            device_id="dev-1", signing_public_key="cHVi")
    assert Config.load(conf) == config
    assert (conf / "ca.crt").read_bytes() == ca.read_bytes()

    key = serialization.load_pem_private_key((conf / "device.key").read_bytes(), None)
    cert = x509.load_pem_x509_certificate((conf / "device.crt").read_bytes())
    raw = serialization.Encoding.Raw, serialization.PublicFormat.Raw
    assert cert.public_key().public_bytes(*raw) == key.public_key().public_bytes(*raw)
    if os.name == "posix":
        assert stat.S_IMODE((conf / "device.key").stat().st_mode) == 0o600


def test_reports_the_servers_reason(tmp_path, ca):
    with pytest.raises(EnrolError, match="422: That code is wrong or has expired. Invalid or expired code."):
        enrol("https://x:8443", "nope", ca, tmp_path / "etc", http=FakeServer(status=422))
    assert not (tmp_path / "etc").exists()


def test_refuses_a_certificate_for_another_key(tmp_path, ca):
    with pytest.raises(EnrolError, match="different key"):
        enrol("https://x:8443", "code", ca, tmp_path / "etc", http=FakeServer(wrong_key=True))


def test_needs_https(tmp_path, ca):
    with pytest.raises(EnrolError, match="https"):
        enrol("http://x:8080", "code", ca, tmp_path / "etc", http=FakeServer())


def test_explains_a_server_certificate_mismatch(tmp_path, ca):
    class BadTls:
        def post(self, *a, **kw):
            raise requests.exceptions.SSLError("hostname mismatch")

    with pytest.raises(EnrolError, match="doesn't list this address"):
        enrol("https://x:8443", "code", ca, tmp_path / "etc", http=BadTls())
