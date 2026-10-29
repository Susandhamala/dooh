#!/usr/bin/env bash
# Lab certificate authority for device mutual TLS. Not for production keys.
#   lab-ca.sh init              create CA + server cert in infra/ca/out
#   lab-ca.sh device <name>     issue a device cert (key generated here only for emulated screens;
#                               real devices generate their own key and send a CSR)
set -euo pipefail
cd "$(dirname "$0")"
OUT=out
mkdir -p "$OUT"
umask 077

case "${1:-}" in
  init)
    openssl req -x509 -newkey ed25519 -nodes -days 3650 -subj "/CN=Rentall Screen Ads Lab CA" \
      -keyout "$OUT/ca.key" -out "$OUT/ca.crt"
    openssl req -newkey ed25519 -nodes -subj "/CN=localhost" \
      -keyout "$OUT/server.key" -out "$OUT/server.csr"
    openssl x509 -req -in "$OUT/server.csr" -CA "$OUT/ca.crt" -CAkey "$OUT/ca.key" -CAcreateserial \
      -days 825 -extfile <(printf "subjectAltName=DNS:localhost,DNS:web\nextendedKeyUsage=serverAuth") \
      -out "$OUT/server.crt"
    chmod 644 "$OUT/ca.crt" "$OUT/server.crt" "$OUT/server.key"
    echo "CA and server cert written to $(pwd)/$OUT"
    ;;
  device)
    name="${2:?device name required}"
    openssl req -newkey ed25519 -nodes -subj "/CN=$name" \
      -keyout "$OUT/$name.key" -out "$OUT/$name.csr"
    openssl x509 -req -in "$OUT/$name.csr" -CA "$OUT/ca.crt" -CAkey "$OUT/ca.key" -CAcreateserial \
      -days 365 -extfile <(printf "extendedKeyUsage=clientAuth") -out "$OUT/$name.crt"
    echo "fingerprint: $(openssl x509 -in "$OUT/$name.crt" -noout -fingerprint -sha1 | cut -d= -f2 | tr -d : | tr A-F a-f)"
    ;;
  *)
    echo "usage: $0 init | device <name>" >&2; exit 1 ;;
esac
