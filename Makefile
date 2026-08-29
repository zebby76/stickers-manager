#!/usr/bin/make -f
# Stickers Manager — dev workflow & packaging.
# All PHP tooling runs in smalswebtech/base-php:8.5-cli-dev via bin/dev (no local PHP).

.DEFAULT_GOAL := help
.PHONY: help build-app build-image bake up down restart ps logs \
        composer install console cc diff migrate fixtures db-reset db-shell shell test \
        release-info release retag tag-restore notes build-version

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
# var/cache/prod is gitignored and NOT invalidated by the commands below, so a stale one keeps
# resolving classes that no longer exist (a controller deleted in 1.4.0 kept breaking
# asset-map:compile: 'Class App\Controller\GoogleController does not exist'). Rebuild it clean.
build-app: ## Build vendor + assets (Composer + AssetMapper) for the image
	rm -rf var/cache/prod
	$(DEV) composer install --no-interaction --no-scripts --prefer-dist --optimize-autoloader --ignore-platform-req=ext-opentelemetry
	APP_ENV=prod $(DEV) php bin/console importmap:install
	APP_ENV=prod $(DEV) php bin/console asset-map:compile
	APP_ENV=prod $(DEV) php bin/console pwa:compile

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

## —— Release & Tag ————————————————————————————————————————————————————————————
# Versioning is git-tag driven: there is no VERSION file, the tag IS the version.
# Pushing a tag `x.y.z` makes docker.yml publish zebby76/stickers-manager:x.y.z (+ -dev)
# and release.yml create the GitHub Release. So `release` stops after pushing the tag --
# it deliberately does NOT call `gh release create`, which would race that workflow.
#
# Ritual:  edit CHANGELOG.md (move [Unreleased] under a new [x.y.z] heading)
#          then `make release VERSION=x.y.z`  (the commit sweeps that edit in)

# Canonical remote: 'upstream' when working from a fork, else 'origin' (the real repo).
RELEASE_REMOTE ?= $(shell git remote | grep -qx upstream && echo upstream || echo origin)
# GitHub owner/repo slug derived from the canonical remote URL (for `gh --repo`).
REPO           ?= $(shell git config --get remote.$(RELEASE_REMOTE).url | sed -E 's#^(git@[^:]+:|https?://[^/]+/)##; s#\.git$$##')
# Commit/ref a tag should point at (default: current HEAD).
REF            ?= HEAD
# Previous tag for release notes (default: auto-detected as the next-lower semver tag).
SINCE          ?=
# Set YES=1 to skip confirmation prompts on destructive operations.
YES            ?=

# Shell snippet: print the semver tag immediately below VERSION (works whether or not VERSION is already a tag).
PREV_TAG = (git tag --sort=-v:refname | grep -E '^[0-9]+\.[0-9]+\.[0-9]+$$'; echo $(VERSION)) | sort -rV | uniq | awk -v v=$(VERSION) '$$0==v{p=1;next} p{print;exit}'

release-info: ## Show the detected remote, repo slug, branch and latest tags
	@echo "canonical remote : $(RELEASE_REMOTE)"
	@echo "gh repo slug     : $(REPO)"
	@echo "current branch   : $$(git rev-parse --abbrev-ref HEAD)"
	@echo "latest tags      : $$(git tag --sort=-v:refname | grep -E '^[0-9]+\.[0-9]+\.[0-9]+$$' | head -5 | tr '\n' ' ')"

release: ## Cut a release:  make release VERSION=1.8.0 [YES=1]  (commit + signed tag + push)
	@[ -n "$(VERSION)" ] || { echo "VERSION=x.y.z required" >&2; exit 1; }
	@b=$$(git rev-parse --abbrev-ref HEAD); [ "$$b" = main ] || { echo "release only from main (on $$b)" >&2; exit 1; }
	@git rev-parse "$(VERSION)" >/dev/null 2>&1 && { echo "tag $(VERSION) already exists -> use 'make retag VERSION=$(VERSION)'" >&2; exit 1; } || true
	@u=$$(git ls-files --others --exclude-standard); [ -z "$$u" ] || { echo "untracked files present -- 'git commit -a' would SKIP them:" >&2; echo "$$u" | sed 's/^/  /' >&2; echo "git add them (or gitignore them) before releasing" >&2; exit 1; }
	@if [ -z "$(YES)" ]; then printf "Release $(VERSION) from main to $(RELEASE_REMOTE) ($(REPO))? [y/N] "; read a; [ "$$a" = y ] || [ "$$a" = Y ] || { echo Aborted >&2; exit 1; }; fi
	@git commit -S -a -m "chore(release): $(VERSION)" || echo "No changes to commit."
	@git tag -s -m "Version $(VERSION)" $(VERSION)
	@git push $(RELEASE_REMOTE) main
	@git push $(RELEASE_REMOTE) refs/tags/$(VERSION)
	@echo "tag $(VERSION) pushed -> docker.yml builds the image, release.yml publishes the GitHub Release"
	@echo "   watch: gh run list --repo $(REPO) -L 3"

retag: ## Move an existing tag and re-fire the build:  make retag VERSION=1.8.0 [REF=HEAD]
	@[ -n "$(VERSION)" ] || { echo "VERSION=x.y.z required" >&2; exit 1; }
	@b=$$(git rev-parse --abbrev-ref HEAD); [ "$$b" = main ] || { echo "retag only from main (on $$b)" >&2; exit 1; }
	@sha=$$(git rev-parse --short "$(REF)"); \
	 echo "retag $(VERSION) -> $$sha on $(RELEASE_REMOTE) ($(REPO))"; \
	 if [ -z "$(YES)" ]; then printf "Proceed (delete+recreate the tag, triggers a rebuild)? [y/N] "; read a; [ "$$a" = y ] || [ "$$a" = Y ] || { echo Aborted >&2; exit 1; }; fi
	@if [ "$$(git rev-parse $(REF))" = "$$(git rev-parse HEAD)" ]; then git push $(RELEASE_REMOTE) main; fi
	@git push $(RELEASE_REMOTE) :refs/tags/$(VERSION) 2>/dev/null || true
	@git tag -d $(VERSION) 2>/dev/null || true
	@git tag -s -m "Version $(VERSION)" $(VERSION) $(REF)
	@git push $(RELEASE_REMOTE) refs/tags/$(VERSION)
	@echo "tag $(VERSION) pushed -> rebuild triggered (run 'make notes VERSION=$(VERSION)' to refresh the release notes)"

tag-restore: ## Force-push the LOCAL tag to the remote (repair a moved tag):  make tag-restore VERSION=1.8.0
	@[ -n "$(VERSION)" ] || { echo "VERSION=x.y.z required" >&2; exit 1; }
	@git rev-parse "$(VERSION)" >/dev/null 2>&1 || { echo "no local tag $(VERSION)" >&2; exit 1; }
	@sha=$$(git rev-parse --short "$(VERSION)^{commit}"); \
	 echo "force-push local tag $(VERSION) ($$sha) -> $(RELEASE_REMOTE) ($(REPO))"; \
	 if [ -z "$(YES)" ]; then printf "Proceed? [y/N] "; read a; [ "$$a" = y ] || [ "$$a" = Y ] || { echo Aborted >&2; exit 1; }; fi
	@git push --force $(RELEASE_REMOTE) refs/tags/$(VERSION)
	@echo "restored (a tag push may NOT rebuild an already-built commit -> use 'make build-version VERSION=$(VERSION)')"

notes: ## Regenerate the GitHub release notes:  make notes VERSION=1.8.0 [SINCE=1.7.1]
	@[ -n "$(VERSION)" ] || { echo "VERSION=x.y.z required" >&2; exit 1; }
	@prev="$(SINCE)"; [ -n "$$prev" ] || prev=$$( $(PREV_TAG) ); \
	 [ -n "$$prev" ] || { echo "no previous tag found; pass SINCE=x.y.z" >&2; exit 1; }; \
	 gh api repos/$(REPO)/releases/generate-notes -f tag_name=$(VERSION) -f previous_tag_name=$$prev --jq .body \
	   | gh release edit $(VERSION) --repo $(REPO) --notes-file - >/dev/null && echo "notes for $(VERSION) regenerated from $$prev"

build-version: ## Trigger a build without tagging:  make build-version VERSION=1.8.0
	@[ -n "$(VERSION)" ] || { echo "VERSION=x.y.z required" >&2; exit 1; }
	@gh workflow run docker.yml --repo $(REPO) -f version=$(VERSION) && echo "workflow_dispatch sent for $(VERSION) (runs the 'main' path, not the 'tag' path)"
