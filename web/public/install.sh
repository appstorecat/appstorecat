#!/bin/sh
# AppStoreCat installer
# Usage: curl -sSL https://appstore.cat/install.sh | sh
# Source:  https://github.com/appstorecat/appstorecat/blob/master/web/public/install.sh

set -eu

REPO_URL="${APPSTORECAT_REPO_URL:-https://github.com/appstorecat/appstorecat.git}"
INSTALL_DIR="${APPSTORECAT_DIR:-appstorecat}"
ENV_EXAMPLE=".env.development.example"
ENV_FILE=".env"
HEALTHCHECK_URL="http://localhost:7461"
HEALTHCHECK_TIMEOUT=120
PORTS="7460 7461 7462 7463 7464 7465"

# ─── ANSI colors (TTY only) ──────────────────────────────────
if [ -t 1 ] && [ -z "${NO_COLOR:-}" ]; then
  C_RESET=$(printf '\033[0m')
  C_BOLD=$(printf '\033[1m')
  C_GREEN=$(printf '\033[32m')
  C_YELLOW=$(printf '\033[33m')
  C_RED=$(printf '\033[31m')
  C_DIM=$(printf '\033[2m')
else
  C_RESET=''; C_BOLD=''; C_GREEN=''; C_YELLOW=''; C_RED=''; C_DIM=''
fi

step()    { printf '%s[%s/%s]%s %s\n' "$C_BOLD" "$1" "$2" "$C_RESET" "$3"; }
ok()      { printf '  %s✓%s %s\n' "$C_GREEN" "$C_RESET" "$1"; }
warn()    { printf '  %s!%s %s\n' "$C_YELLOW" "$C_RESET" "$1"; }
fail()    { printf '\n%sError:%s %s\n' "$C_RED" "$C_RESET" "$1" >&2; exit 1; }
info()    { printf '  %s%s%s\n' "$C_DIM" "$1" "$C_RESET"; }

# ─── Banner ──────────────────────────────────────────────────
printf '\n'
printf '  %sAppStoreCat installer%s\n' "$C_BOLD" "$C_RESET"
printf '  %sOpen-source App Store & Google Play intelligence%s\n' "$C_DIM" "$C_RESET"
printf '\n'

# ─── 1/5 Dependencies ───────────────────────────────────────
step 1 5 "Checking dependencies"

for cmd in git docker make curl; do
  if ! command -v "$cmd" >/dev/null 2>&1; then
    fail "'$cmd' is required but not installed.

Install steps:
  macOS:  brew install $cmd
  Linux:  Use your package manager (apt, dnf, pacman, ...)
  Docker: https://docs.docker.com/get-docker/"
  fi
done
ok "git, docker, make, curl present"

# Docker Compose v2 (plugin) check
if ! docker compose version >/dev/null 2>&1; then
  fail "Docker Compose v2 plugin is required.
'docker compose' command not found. See https://docs.docker.com/compose/install/"
fi
ok "docker compose v2 plugin present"

# Docker daemon running?
if ! docker info >/dev/null 2>&1; then
  fail "Docker daemon is not running. Start Docker Desktop / dockerd and re-run."
fi
ok "docker daemon is running"

# Port availability check (warn-only, not fatal — user may want to override later)
PORT_CONFLICT=0
for port in $PORTS; do
  if lsof -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1; then
    warn "Port $port is already in use (see 'lsof -iTCP:$port'). You'll need to edit .env to change it."
    PORT_CONFLICT=1
  fi
done
[ "$PORT_CONFLICT" -eq 0 ] && ok "ports $PORTS are free"

printf '\n'

# ─── 2/5 Clone or update ────────────────────────────────────
step 2 5 "Fetching source"

if [ -d "$INSTALL_DIR" ]; then
  info "Directory '$INSTALL_DIR' already exists — pulling latest"
  ( cd "$INSTALL_DIR" && git pull --ff-only ) || fail "git pull failed in '$INSTALL_DIR'. Resolve manually and re-run."
  ok "updated existing checkout"
else
  git clone --depth 1 "$REPO_URL" "$INSTALL_DIR" || fail "git clone failed."
  ok "cloned to $(pwd)/$INSTALL_DIR"
fi

cd "$INSTALL_DIR"

printf '\n'

# ─── 3/5 Environment setup ──────────────────────────────────
step 3 5 "Preparing .env"

if [ -f "$ENV_FILE" ]; then
  ok ".env already exists — leaving as-is"
else
  if [ ! -f "$ENV_EXAMPLE" ]; then
    fail "$ENV_EXAMPLE missing in repo. Cannot continue."
  fi
  cp "$ENV_EXAMPLE" "$ENV_FILE"
  ok "copied $ENV_EXAMPLE → $ENV_FILE"
fi

printf '\n'

# ─── 4/5 Build & install ────────────────────────────────────
step 4 5 "Building containers and installing dependencies (this can take 3–8 minutes)"

if ! make setup; then
  fail "make setup failed.

Common causes:
  - Docker daemon stopped during build (check 'docker info')
  - Insufficient disk space ('docker system df')
  - Network blocked composer/npm registry
  - Port already bound (see warnings above)

Inspect logs:
  cd $INSTALL_DIR && docker compose logs"
fi
ok "containers built, dependencies installed, database migrated"

printf '\n'

# ─── 5/5 Start & health check ───────────────────────────────
step 5 5 "Starting all services"

make dev >/dev/null || fail "make dev failed. Run 'cd $INSTALL_DIR && docker compose logs' for details."
ok "containers started"

info "Waiting for web frontend to respond on $HEALTHCHECK_URL (timeout: ${HEALTHCHECK_TIMEOUT}s)"
elapsed=0
while [ "$elapsed" -lt "$HEALTHCHECK_TIMEOUT" ]; do
  if curl -fsS --max-time 3 "$HEALTHCHECK_URL" >/dev/null 2>&1; then
    ok "web frontend responding"
    break
  fi
  sleep 3
  elapsed=$((elapsed + 3))
done

if [ "$elapsed" -ge "$HEALTHCHECK_TIMEOUT" ]; then
  warn "Frontend did not respond within ${HEALTHCHECK_TIMEOUT}s."
  warn "Containers are running but may still be initializing. Inspect with:"
  printf '    %scd %s && docker compose logs -f%s\n' "$C_DIM" "$INSTALL_DIR" "$C_RESET"
fi

# ─── Done ───────────────────────────────────────────────────
printf '\n'
printf '  %s%sAppStoreCat is installed.%s\n' "$C_BOLD" "$C_GREEN" "$C_RESET"
printf '\n'
printf '  Open:        %s%s%s\n' "$C_BOLD" "$HEALTHCHECK_URL" "$C_RESET"
printf '  Installed:   %s%s/%s%s\n' "$C_DIM" "$(pwd | sed "s|/$INSTALL_DIR$||")" "$INSTALL_DIR" "$C_RESET"
printf '\n'
printf '  Useful commands (run from %s%s%s):\n' "$C_BOLD" "$INSTALL_DIR" "$C_RESET"
printf '    make ps        Service status\n'
printf '    make logs      Follow logs\n'
printf '    make down      Stop services\n'
printf '    make restart   Restart services\n'
printf '\n'
printf '  Next:\n'
printf '    1. Open %s and create an account\n' "$HEALTHCHECK_URL"
printf '    2. Add Claude Code MCP integration (settings → API tokens)\n'
printf '    3. Star the repo: %shttps://github.com/appstorecat/appstorecat%s\n' "$C_DIM" "$C_RESET"
printf '\n'
