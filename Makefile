.PHONY: build up down restart shell tinker migrate test test-all test-functional test-balance balance-l1 balance-l2 front-dev front-build game-websocket

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

test-all:
	docker compose exec app php artisan test

test-functional:
	docker compose exec app php artisan test --testsuite=Unit,Feature

test-balance:
	docker compose exec app php artisan test --testsuite=Balance

balance-l1:
	docker compose exec app php artisan test --testsuite=BalanceL1

balance-l2:
	docker compose exec app php artisan test --testsuite=BalanceL2

front-dev:
	docker compose exec app npm run dev

front-build:
	docker compose exec app npm run build

game-websocket:
	docker compose exec app php artisan reverb:start
