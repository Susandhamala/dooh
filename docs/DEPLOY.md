# Deploying ads.rentallnepal.com

One Linux server (Ubuntu 24.04 or similar, 2 vCPU, 4 GB RAM or more, 50 GB+ disk for ads) runs the
whole stack in Docker. A host Nginx holds the public TLS certificate. Screens connect to port 8443
directly, because their mutual TLS can't pass through a proxy.

```
people ──HTTPS 443──▶ host Nginx ──▶ 127.0.0.1:8080 ┐
                                                    ├─ web (Nginx) ─▶ api (PHP-FPM) ─▶ postgres, redis
screens ──mTLS 8443─────────────────────────────────┘                  queue, scheduler (signs playlists)
```

## 1. Server

```bash
# Docker Engine + Compose plugin: https://docs.docker.com/engine/install/ubuntu/
sudo apt install -y nginx certbot python3-certbot-nginx make git openssl
sudo ufw allow OpenSSH && sudo ufw allow 80,443,8443/tcp && sudo ufw enable
git clone https://github.com/Susandhamala/dooh.git /srv/dooh && cd /srv/dooh
```

Point the DNS `A` record for `ads.rentallnepal.com` at the server first.

## 2. Settings

```bash
cp .env.example .env                              # DB_PASSWORD=$(openssl rand -hex 24)
cp api/.env.production.example api/.env           # fill in every <...>
docker run --rm php:8.4-cli php -r 'echo "base64:".base64_encode(random_bytes(32)), "\n";'   # APP_KEY
openssl rand -hex 32                              # JWT_SECRET
chmod 600 .env api/.env
```

`DB_PASSWORD` must be the same in both files. `APP_ENV=production` turns off every lab shortcut
(password login, codes on screen, simulated plays), whatever the other settings say.

## 3. Keys and certificates

```bash
infra/ca/lab-ca.sh init DNS:ads.rentallnepal.com
```

This writes `infra/ca/out/`:

- the device CA, which signs each screen's certificate when it enrols;
- the 8443 server certificate;
- the Ed25519 key that signs playlists, plus `manifest.env` with its public half.

The keys are made on the server and never leave it, except in an offline backup. If the CA key is
lost, every screen has to enrol again. If the signing key leaks, anyone can tell a screen what to play.

## 4. Start

```bash
make prod                     # build and start; the api container runs migrations first
docker compose ps             # every service "running" and api/web "healthy"
docker compose exec api php artisan db:seed --force          # roles: advertiser, owner, admin
docker compose exec api php artisan dooh:admin-login admin   # admin account for /4dmin (asks for a password)
```

## 5. Public TLS

```bash
sudo cp infra/nginx/host-ads.rentallnepal.com.conf /etc/nginx/sites-available/ads.rentallnepal.com
sudo ln -s /etc/nginx/sites-available/ads.rentallnepal.com /etc/nginx/sites-enabled/
sudo certbot --nginx -d ads.rentallnepal.com
sudo nginx -t && sudo systemctl reload nginx
```

The web container then sends HSTS and sees each visitor's real address, so rate limits apply per
visitor.

## 6. Updates

```bash
git pull && make prod
```

New images are built, containers are recreated and migrations run. Config and routes are cached
again at start.

## 7. Backups

```bash
make backup                   # backups/<date>/dooh.dump + media.tar.gz
```

Run it every night from cron, and copy `backups/` and `infra/ca/out/` to another machine:

```
15 2 * * * cd /srv/dooh && make backup >> /var/log/dooh-backup.log 2>&1
```

Restore with `make restore DIR=backups/2026-11-19`. Try a restore on a spare machine before launch.

## 8. Check it is healthy

| What | How |
|---|---|
| Services up | `docker compose ps` (api and web report health) |
| Errors | `docker compose logs --since 1h api queue scheduler` |
| Playlists signed | `docker compose logs scheduler \| grep manifests` |
| Headers | `curl -sI https://ads.rentallnepal.com \| grep -iE 'strict\|content-security'` |

## Before real customers

These need accounts or contracts, so they can't be finished in code alone:

| Item | Now | Needed |
|---|---|---|
| Phone sign-in codes | `SMS_DRIVER=log`: codes are only written to the log | an SMS gateway account (Sparrow SMS or Aakash SMS) and its driver |
| Wallet top-ups | credited at once | eSewa / Khalti merchant accounts and their payment callbacks |
| Sign-in with RentALL | ready | `RENTALL_SSO_CLIENT_SECRET` from rentallnepal.com |
| Map tiles | OpenStreetMap's public tile server | fine for low traffic; a tile provider key for more |
