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

## Run it on your computer

You need PHP 8.3+ (with `pdo_pgsql`, `sodium`, `gd`), Composer, Node 22, Python 3.11+ and PostgreSQL.

```bash
git clone https://github.com/Susandhamala/dooh.git
cd dooh
```

Database (once):

```bash
createuser -P dooh          # choose a password
createdb -O dooh dooh
createdb -O dooh dooh_test
```

API:

```bash
cd api
composer install
cp .env.example .env        # set DB_HOST=127.0.0.1, DB_PASSWORD, LAB_LOCAL_LOGIN=true
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
php artisan serve           # http://localhost:8000
```

Portal (second terminal):

```bash
cd portal
npm install
cp .env.example .env
npm run dev                 # http://localhost:5173, proxies /api to :8000
```

Player tests:

```bash
cd player
python3 -m venv .venv && .venv/bin/pip install -e '.[dev]'
.venv/bin/pytest
```

API tests use the `dooh_test` database with the credentials in `api/phpunit.xml`.

## Quick start (Docker lab)

```bash
cp .env.example .env
make up        # build and start the stack
make migrate   # run database migrations
make test      # API + player tests
```

Portal: http://localhost:8080 · API: http://localhost:8080/api/v1 · Device endpoint (mTLS): https://localhost:8443
