.PHONY: build up down restart shell tinker migrate test dev

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose down
	docker compose up -d

shell:
	docker compose exec app bash

tinker:
	docker compose exec app php artisan tinker

migrate:
	docker compose exec app php artisan migrate

test:
	docker compose exec app php artisan test

dev:
	docker compose exec app npm run dev
