#!/usr/bin/env bash
# Certificate authority for device mutual TLS, and the manifest signing key. For production, run it
# on the server itself (docs/DEPLOY.md), so the private keys are made there and never copied around.
#   lab-ca.sh init [SAN...]     create the CA, server cert and manifest signing key in infra/ca/out
#   lab-ca.sh server [SAN...]   reissue only the server cert, e.g. with the LAN address a Raspberry Pi
#                               connects to:  lab-ca.sh server IP:192.168.1.50
#                               (same CA, so devices that are already enrolled keep working)
#   lab-ca.sh manifest-key      create the manifest signing key if it is missing
# Devices get their certificates by enrolling with a one-time code from the portal; their private
# key is made on the device and never comes here.
set -euo pipefail
cd "$(dirname "$0")"
OUT=out
mkdir -p "$OUT"
umask 077
# Git Bash on Windows rewrites "/CN=..." into a file path; this turns that off.
export MSYS_NO_PATHCONV=1

# Extensions go through files, not <(...): Windows openssl cannot open bash's /dev/fd pipes.
server_cert() {
  local sans="DNS:localhost,DNS:web"
  for san in "$@"; do sans="$sans,$san"; done
  openssl req -newkey ed25519 -nodes -subj "/CN=localhost" \
    -keyout "$OUT/server.key" -out "$OUT/server.csr"
  printf 'subjectAltName=%s\nbasicConstraints=critical,CA:FALSE\nkeyUsage=critical,digitalSignature\nextendedKeyUsage=serverAuth\nsubjectKeyIdentifier=hash\nauthorityKeyIdentifier=keyid\n' "$sans" > "$OUT/server.ext"
  openssl x509 -req -in "$OUT/server.csr" -CA "$OUT/ca.crt" -CAkey "$OUT/ca.key" -CAcreateserial \
    -days 825 -extfile "$OUT/server.ext" -out "$OUT/server.crt"
  chmod 644 "$OUT/server.crt" "$OUT/server.key"
  echo "server cert for $sans written to $(pwd)/$OUT (restart the web container to load it)"
}

# A random 32-byte Ed25519 seed (base64), and its public key for the API in manifest.env.
manifest_key() {
  if [ ! -f "$OUT/manifest.key" ]; then
    openssl rand -base64 32 > "$OUT/manifest.key"
  fi
  # Wrap the seed in a PKCS#8 header so openssl can derive the public key from it.
  local der="$OUT/manifest.der"
  { printf '\060\056\002\001\000\060\005\006\003\053\145\160\004\042\004\040'; openssl base64 -d -in "$OUT/manifest.key"; } > "$der"
  local pub
  pub=$(openssl pkey -inform DER -in "$der" -pubout -outform DER | tail -c 32 | openssl base64 -A)
  rm -f "$der"
  printf 'MANIFEST_SIGNING_PUBKEY=%s\n' "$pub" > "$OUT/manifest.env"
  chmod 644 "$OUT/manifest.env"
  echo "manifest signing key in $(pwd)/$OUT/manifest.key, public key $pub"
}

case "${1:-}" in
  init)
    shift
    if [ -f "$OUT/ca.key" ] && [ "${FORCE:-}" != 1 ]; then
      echo "$OUT/ca.key exists; a new CA invalidates every enrolled device. Use FORCE=1 to replace it." >&2; exit 1
    fi
    # Key usage on the CA is required by strict verifiers (Python 3.13 and later).
    openssl req -x509 -newkey ed25519 -nodes -days 3650 -subj "/CN=Rentall Screen Ads Lab CA" \
      -addext "basicConstraints=critical,CA:TRUE,pathlen:0" -addext "keyUsage=critical,keyCertSign,cRLSign" \
      -keyout "$OUT/ca.key" -out "$OUT/ca.crt"
    chmod 644 "$OUT/ca.crt"
    server_cert "$@"
    manifest_key
    echo "CA written to $(pwd)/$OUT"
    ;;
  server)
    shift
    server_cert "$@"
    ;;
  manifest-key)
    manifest_key
    ;;
  *)
    echo "usage: $0 init [SAN...] | server [SAN...] | manifest-key" >&2; exit 1 ;;
esac
