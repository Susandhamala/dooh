# DOOH (ads.rentallnepal.com)

Plan and threat model: docs/PLAN.md. Read it before designing a feature.

## Conventions

- **API layering:** Route → Middleware → Controller → Form Request → Service → Repository → Model → Resource. Enforced by `api/tests/Arch`. Controllers never touch models or the DB.
- **Responses:** always `App\Http\Responses\ApiResponse` → `{success, message, data, errors, meta}`. Errors are rendered into the same envelope in `bootstrap/app.php`.
- **IDs:** integer `id` is internal only; expose `uuid`. "Not yours" and "doesn't exist" both return 404.
- **Tenancy:** every query on tenant data is scoped by organisation. Every new endpoint gets a cross-tenant test in `api/tests/Security`.
- **Device routes** live under `/device/v1` and are only reachable through the mTLS server block (port 8443); Laravel must check `DEVICE_CERT_FINGERPRINT` against `devices` (not revoked).
- **Manifests:** `{payload: base64(json bytes), signature: base64(ed25519(payload bytes))}`. Integers only in the payload (Unix seconds). The player (`player/dooh_player/manifest.py`) is the reference verifier.
- **Portal:** Vue 3 + TS, Pinia, all HTTP through `src/lib/http.ts`.

## Commands

```bash
make test          # api (pest) + portal (vue-tsc, vitest) + player (pytest)
make up            # docker stack; make lab adds emulated screens
infra/ca/lab-ca.sh device <name>   # issue a lab device certificate
```
