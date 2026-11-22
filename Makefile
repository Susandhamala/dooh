.PHONY: up down lab ca migrate test test-api test-portal test-player shell logs prod backup restore

# Production (see docs/DEPLOY.md): host Nginx in front, migrations run when the api starts.
PROD = docker compose -f docker-compose.yml -f docker-compose.prod.yml
TODAY := $(shell date +%F)

up: ca
	docker compose up -d --build

down:
	docker compose down

lab: ca
	docker compose --profile lab up -d --build

ca: infra/ca/out/ca.crt infra/ca/out/manifest.key

infra/ca/out/ca.crt:
	infra/ca/lab-ca.sh init

# Older lab setups made before manifests were signed.
infra/ca/out/manifest.key:
	infra/ca/lab-ca.sh manifest-key

migrate:
	docker compose exec api php artisan migrate

test: test-api test-portal test-player

test-api:
	cd api && ./vendor/bin/pest

test-portal:
	cd portal && npm run typecheck && npm test

test-player:
	cd player && python -m pytest -q

shell:
	docker compose exec api sh

logs:
	docker compose logs -f

prod: ca
	$(PROD) up -d --build --remove-orphans

# Database dump plus uploaded ads and documents, into backups/<date>/. Copy them off the server.
backup:
	mkdir -p backups/$(TODAY)
	docker compose exec -T postgres pg_dump -U dooh -Fc dooh > backups/$(TODAY)/dooh.dump
	docker compose exec -T api tar -C storage/app -czf - . > backups/$(TODAY)/media.tar.gz
	@echo "backup written to backups/$(TODAY)"

# make restore DIR=backups/2026-11-19 (replaces the current data with the backup)
restore:
	test -n "$(DIR)"
	docker compose exec -T postgres pg_restore -U dooh -d dooh --clean --if-exists < $(DIR)/dooh.dump
	docker compose exec -T api tar -C storage/app -xzf - < $(DIR)/media.tar.gz
