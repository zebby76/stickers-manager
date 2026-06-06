#!/usr/bin/make -f
# Stickers Manager — dev workflow & packaging.
# All PHP tooling runs in smalswebtech/base-php:8.5-cli-dev via bin/dev (no local PHP).

.DEFAULT_GOAL := help
.PHONY: help build-app build-image bake up down restart ps logs \
        composer install console cc diff migrate fixtures db-reset db-shell shell test

# —— Config ———————————————————————————————————————————————————————————————————
POSTGRES_USER ?= stickers
POSTGRES_PASSWORD ?= stickers
POSTGRES_DB ?= stickers
POSTGRES_PORT ?= 5432
DOCKER_IMAGE_NAME ?= zebby76/stickers-manager

# DSN for host-side tooling (cli-dev runs with --network host → published Postgres).
DSN_LOCAL = postgresql://$(POSTGRES_USER):$(POSTGRES_PASSWORD)@127.0.0.1:$(POSTGRES_PORT)/$(POSTGRES_DB)?serverVersion=16&charset=utf8
DEV      = ./bin/dev
DEV_DB   = DATABASE_URL="$(DSN_LOCAL)" ./bin/dev

help: ## Show this help
	@grep -E '(^[a-zA-Z0-9_-]+:.*?## .*$$)|(^##)' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}{printf "\033[36m%-16s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[36m##/[33m/'

## —— Packaging ————————————————————————————————————————————————————————————————
build-app: ## Build vendor + assets (Composer + AssetMapper) for the image
	$(DEV) composer install --no-interaction --no-scripts --prefer-dist --optimize-autoloader
	APP_ENV=prod $(DEV) php bin/console importmap:install
	APP_ENV=prod $(DEV) php bin/console asset-map:compile

build-image: build-app ## Build prd + dev Docker images locally
	docker build --target prd -t $(DOCKER_IMAGE_NAME):snapshot .
	docker build --target dev -t $(DOCKER_IMAGE_NAME):snapshot-dev .

bake: build-app ## Build via docker buildx bake (multi-arch, see docker-bake.hcl)
	docker buildx bake

## —— Local dev (compose, bind-mount) ——————————————————————————————————————————
up: ## Start the dev stack (postgres + app + adminer)
	docker compose up -d

down: ## Stop the dev stack
	docker compose down

restart: ## Restart the app container
	docker compose restart app

ps: ## Show containers
	docker compose ps

logs: ## Tail app logs
	docker compose logs -f app

## —— Composer / Symfony console ———————————————————————————————————————————————
composer: ## Run composer (ARGS="require ...")
	$(DEV) composer $(ARGS)

install: ## composer install (with dev deps, for local dev)
	$(DEV) composer install

console: ## Run bin/console (ARGS="cache:clear")
	$(DEV_DB) php bin/console $(ARGS)

cc: ## Clear Symfony cache
	$(DEV_DB) php bin/console cache:clear

## —— Doctrine —————————————————————————————————————————————————————————————————
diff: ## Generate a migration from entity changes
	$(DEV_DB) php bin/console make:migration

migrate: ## Run pending migrations
	$(DEV_DB) php bin/console doctrine:migrations:migrate --no-interaction

fixtures: ## Load data fixtures (DROPS data)
	$(DEV_DB) php bin/console doctrine:fixtures:load --no-interaction

db-reset: ## Drop, create, migrate and load fixtures
	$(DEV_DB) php bin/console doctrine:database:drop --force --if-exists
	$(DEV_DB) php bin/console doctrine:database:create
	$(DEV_DB) php bin/console doctrine:migrations:migrate --no-interaction
	$(DEV_DB) php bin/console doctrine:fixtures:load --no-interaction

db-shell: ## psql shell into the database
	docker compose exec database psql -U $(POSTGRES_USER) -d $(POSTGRES_DB)

## —— Shells / tests ———————————————————————————————————————————————————————————
shell: ## Interactive shell in the cli-dev tooling container
	$(DEV) bash

test: ## Run the test suite (APP_ENV=test)
	APP_ENV=test $(DEV_DB) php bin/phpunit
