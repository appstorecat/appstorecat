VERSION := $(shell cat VERSION)
REGISTRY := ghcr.io/appstorecat
SERVICES := backend frontend scraper-appstore scraper-gplay
BACKEND := docker compose exec appstorecat-backend
FRONTEND := docker compose exec appstorecat-frontend

# ─── Pass-through engine ─────────────────────────────────────
# Enables: make artisan migrate:fresh --seed
#          make composer require foo/bar
#          make npm install axios
PASS_THROUGH := artisan composer npm
FIRST_GOAL := $(firstword $(MAKECMDGOALS))
EXTRA_ARGS = $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))

# When in pass-through mode, swallow extra args so Make ignores them
ifneq ($(filter $(FIRST_GOAL),$(PASS_THROUGH)),)
%:
	@:
endif

.PHONY: setup dev down restart logs ps build \
        dev-backend dev-frontend dev-gplay dev-appstore \
        install key artisan composer tinker shell npm \
        test test-backend test-gplay test-appstore lint pint lint-frontend \
        swagger api-generate api \
        logs-backend logs-frontend logs-gplay logs-appstore \
        mysql redis-cli \
        clean nuke \
        build-prod release version

# ─── Shortcuts (disabled during pass-through) ────────────────
ifeq ($(filter $(FIRST_GOAL),$(PASS_THROUGH)),)
.PHONY: migrate seed fresh cache-clear route-list queue-restart schedule

migrate:
	$(BACKEND) php artisan migrate

seed:
	$(BACKEND) php artisan db:seed

fresh:
	$(BACKEND) php artisan migrate:fresh --seed

cache-clear:
	$(BACKEND) php artisan optimize:clear

route-list:
	$(BACKEND) php artisan route:list

queue-restart:
	$(BACKEND) php artisan queue:restart

schedule:
	$(BACKEND) php artisan schedule:run
endif

# ─── Full Stack ───────────────────────────────────────────────

## First time setup: build containers, install deps, generate key, run migrations
setup: build install key migrate
	@echo "Setup complete. Run 'make dev' to start."

## Start all services
dev:
	docker compose up -d

## Stop all services
down:
	docker compose down

## Restart all services
restart: down dev

## Show logs (follow)
logs:
	docker compose logs -f

## Show service status
ps:
	docker compose ps

## Build/rebuild containers
build:
	docker compose build

# ─── Individual Services ──────────────────────────────────────

dev-backend:
	docker compose up -d appstorecat-backend appstorecat-mysql appstorecat-redis

dev-frontend:
	docker compose up -d appstorecat-frontend

dev-gplay:
	docker compose up -d appstorecat-scraper-gplay

dev-appstore:
	docker compose up -d appstorecat-scraper-appstore

# ─── Backend (Laravel) ────────────────────────────────────────

## Run any artisan command: make artisan migrate:fresh --seed
artisan:
	$(BACKEND) php artisan $(EXTRA_ARGS)

## Run any composer command: make composer require foo/bar
composer:
	$(BACKEND) composer $(EXTRA_ARGS)

## Install all dependencies
install:
	docker compose run --rm appstorecat-backend composer install
	cd frontend && npm install
	cd scraper-appstore && npm install

## Generate APP_KEY (only needed once)
key:
	docker compose run --rm appstorecat-backend php artisan key:generate

## Open tinker
tinker:
	$(BACKEND) php artisan tinker

## Open a shell in the backend container
shell:
	$(BACKEND) sh

# ─── Frontend ────────────────────────────────────────────────

## Run npm command in frontend: make npm install axios
npm:
	$(FRONTEND) npm $(EXTRA_ARGS)

# ─── API Docs ────────────────────────────────────────────────

## Generate Swagger/OpenAPI docs (backend)
swagger:
	$(BACKEND) php artisan l5-swagger:generate

## Generate TypeScript API client from Swagger (frontend)
api-generate:
	cd frontend && npx orval

## Full API pipeline: swagger + generate client
api: swagger api-generate

# ─── Code Quality ────────────────────────────────────────────

## Run all linters
lint: pint lint-frontend

## PHP code style (backend)
pint:
	$(BACKEND) php ./vendor/bin/pint

## ESLint (frontend)
lint-frontend:
	$(FRONTEND) npx eslint .

# ─── Tests ────────────────────────────────────────────────────

## Run all tests
test: test-backend test-gplay test-appstore

## Backend tests (Pest)
test-backend:
	$(BACKEND) php artisan test

## Google Play scraper tests (pytest)
test-gplay:
	docker compose exec appstorecat-scraper-gplay pytest

## App Store scraper tests (vitest)
test-appstore:
	docker compose exec appstorecat-scraper-appstore npm test

# ─── Logs ─────────────────────────────────────────────────────

logs-backend:
	docker compose logs -f appstorecat-backend

logs-frontend:
	docker compose logs -f appstorecat-frontend

logs-gplay:
	docker compose logs -f appstorecat-scraper-gplay

logs-appstore:
	docker compose logs -f appstorecat-scraper-appstore

# ─── Database ────────────────────────────────────────────────

## Open MySQL CLI
mysql:
	docker compose exec appstorecat-mysql mysql -u$${DB_USERNAME:-sail} -p$${DB_PASSWORD:-password} $${DB_DATABASE:-appstorecat}

## Open Redis CLI
redis-cli:
	docker compose exec appstorecat-redis redis-cli

# ─── Cleanup ──────────────────────────────────────────────────

## Stop and remove containers + volumes
clean:
	docker compose down -v --remove-orphans

## Full nuke: containers, volumes, images
nuke:
	docker compose down -v --remove-orphans --rmi local

# ─── Production ──────────────────────────────────────────────

## Show current version
version:
	@echo $(VERSION)

PLATFORMS := linux/amd64,linux/arm64

## Login to GitHub Container Registry
docker-login:
	@echo $(GHCR_TOKEN) | docker login ghcr.io -u $(GHCR_USER) --password-stdin

## Build and push all production images (multi-platform)
build-prod:
	@echo "Building + pushing v$(VERSION) ($(PLATFORMS))..."
	@for svc in $(SERVICES); do \
		docker buildx build --platform $(PLATFORMS) \
			-t $(REGISTRY)/$$svc:$(VERSION) \
			-t $(REGISTRY)/$$svc:latest \
			-f $$svc/.docker/Dockerfile.prod $$svc --push; \
	done
	@echo "Done."

## Release: bump version, build, push — usage: make release v=1.0.0
release:
ifndef v
	$(error Usage: make release v=1.0.0)
endif
	@echo "$(v)" > VERSION
	@echo "Releasing v$(v)..."
	@for svc in $(SERVICES); do \
		docker buildx build --platform $(PLATFORMS) \
			-t $(REGISTRY)/$$svc:$(v) \
			-t $(REGISTRY)/$$svc:latest \
			-f $$svc/.docker/Dockerfile.prod $$svc --push; \
	done
	@git add VERSION
	@git commit -m "release: v$(v)"
	@git tag v$(v)
	@git push origin master --tags
	@echo "Released v$(v)"
