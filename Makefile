.PHONY: up down lab ca migrate test test-api test-portal test-player shell logs

up: ca
	docker compose up -d --build

down:
	docker compose down

lab: ca infra/ca/out/player-1.crt
	docker compose --profile lab up -d --build

ca: infra/ca/out/ca.crt

infra/ca/out/ca.crt:
	infra/ca/lab-ca.sh init

infra/ca/out/player-1.crt:
	infra/ca/lab-ca.sh device player-1

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
