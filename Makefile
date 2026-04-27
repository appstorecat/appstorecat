VERSION := $(shell cat VERSION)
REGISTRY := ghcr.io/appstorecat
SERVICES := server web scraper-ios scraper-android
SERVER := docker compose exec appstorecat-server
WEB := docker compose exec appstorecat-web

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
        dev-server dev-web dev-android dev-ios \
        install key artisan composer tinker shell npm \
        lint pint lint-web \
        swagger api-generate api \
        logs-server logs-web logs-android logs-ios \
        mysql redis-cli \
        clean nuke \
        mcp-install mcp-build mcp-dev \
        docs-install docs-dev docs-build docs-preview \
        build-prod release version

# ─── Shortcuts (disabled during pass-through) ────────────────
ifeq ($(filter $(FIRST_GOAL),$(PASS_THROUGH)),)
.PHONY: migrate seed fresh cache-clear route-list queue-restart schedule

migrate:
	$(SERVER) php artisan migrate

seed:
	$(SERVER) php artisan db:seed

fresh:
	$(SERVER) php artisan migrate:fresh --seed

cache-clear:
	$(SERVER) php artisan optimize:clear

route-list:
	$(SERVER) php artisan route:list

queue-restart:
	$(SERVER) php artisan queue:restart

schedule:
	$(SERVER) php artisan schedule:run
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

dev-server:
	docker compose up -d appstorecat-server appstorecat-mysql appstorecat-redis

dev-web:
	docker compose up -d appstorecat-web

dev-android:
	docker compose up -d appstorecat-scraper-android

dev-ios:
	docker compose up -d appstorecat-scraper-ios

# ─── Server (Laravel) ────────────────────────────────────────

## Run any artisan command: make artisan migrate:fresh --seed
artisan:
	$(SERVER) php artisan $(EXTRA_ARGS)

## Run any composer command: make composer require foo/bar
composer:
	$(SERVER) composer $(EXTRA_ARGS)

## Install all dependencies
install:
	docker compose run --rm appstorecat-server composer install
	cd web && npm install
	cd scraper-ios && npm install

## Generate APP_KEY (only needed once)
key:
	docker compose run --rm appstorecat-server php artisan key:generate

## Open tinker
tinker:
	$(SERVER) php artisan tinker

## Open a shell in the server container
shell:
	$(SERVER) sh

# ─── Web ────────────────────────────────────────────────────

## Run npm command in web: make npm install axios
npm:
	$(WEB) npm $(EXTRA_ARGS)

# ─── API Docs ────────────────────────────────────────────────

## Generate Swagger/OpenAPI docs (server)
swagger:
	$(SERVER) php artisan l5-swagger:generate

## Generate TypeScript API client from Swagger (web)
api-generate:
	cd web && npx orval

## Full API pipeline: swagger + generate client
api: swagger api-generate

# ─── Code Quality ────────────────────────────────────────────

## Run all linters
lint: pint lint-web

## PHP code style (server)
pint:
	$(SERVER) php ./vendor/bin/pint

## ESLint (web)
lint-web:
	$(WEB) npx eslint .

# ─── Logs ─────────────────────────────────────────────────────

logs-server:
	docker compose logs -f appstorecat-server

logs-web:
	docker compose logs -f appstorecat-web

logs-android:
	docker compose logs -f appstorecat-scraper-android

logs-ios:
	docker compose logs -f appstorecat-scraper-ios

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

# ─── MCP ─────────────────────────────────────────────────────

## Install MCP server dependencies
mcp-install:
	cd mcp && npm install

## Build MCP server
mcp-build:
	cd mcp && npm run build

## Run MCP server in dev mode
mcp-dev:
	cd mcp && npm run dev

# ─── Docs Site (Astro + Starlight, GitHub Pages) ─────────────

## Install docs site dependencies
docs-install:
	cd docs-site && npm install

## Sync docs/en/ then run docs site dev server (http://localhost:4321)
docs-dev:
	cd docs-site && npm run sync-docs && npm run dev

## Build static docs site to docs-site/dist/
docs-build:
	cd docs-site && npm run sync-docs && npm run build

## Preview the production build locally
docs-preview:
	cd docs-site && npm run preview

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
	@cd mcp && npm version $(v) --no-git-tag-version --allow-same-version && npm run build && npm publish --access public
	@git add VERSION mcp/package.json mcp/package-lock.json
	@git commit -m "release: v$(v)"
	@git tag v$(v)
	@git push origin master --tags
	@gh release create v$(v) --title "v$(v)" --generate-notes
	@echo "Released v$(v)"
