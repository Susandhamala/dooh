# DOOH — ads.rentallnepal.com

Self-service digital out-of-home (DOOH) ad screen marketplace. Advertisers book screen time and
publish ads in three steps (Create, Check, Upload); screen owners approve every campaign; screens
only play content the platform has signed, over mutual TLS.

Full plan: [docs/PLAN.md](docs/PLAN.md).

## Layout

| Path | What |
|---|---|
| `api/` | Laravel 13 API (PHP 8.4) |
| `portal/` | Vue 3 + Vite portal for advertisers, owners and admins |
| `player/` | Python screen player agent (runs on the Pi, or in Docker as an emulated screen) |
| `infra/` | Docker images, Nginx config, lab certificate authority scripts |

## Quick start (lab)

```bash
cp .env.example .env
make up        # build and start the stack
make migrate   # run database migrations
make test      # API + player tests
```

Portal: http://localhost:8080 · API: http://localhost:8080/api/v1 · Device endpoint (mTLS): https://localhost:8443
