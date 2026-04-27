# Install Script

The one-line install script (`curl -sSL https://appstore.cat/install.sh | sh`) bootstraps a complete AppStoreCat development setup on any machine with Docker, git, make, and curl available.

## What it does

1. Verifies dependencies (`git`, `docker`, `make`, `curl`) and that the Docker daemon is running.
2. Verifies Docker Compose v2 plugin is available (`docker compose version`).
3. Warns if any of ports `7460–7465` are already in use.
4. Clones `appstorecat/appstorecat` into `./appstorecat` (or pulls latest if directory exists).
5. Copies `.env.development.example` → `.env` if no `.env` already exists.
6. Runs `make setup` (builds Docker images, installs PHP/Node deps, generates app key, runs DB migrations).
7. Runs `make dev` (starts all services in the background).
8. Polls `http://localhost:7461` until the web frontend responds (timeout: 120s).
9. Prints next steps (open browser, create account, link Claude Code MCP).

The script is **idempotent** — re-running it on an existing checkout pulls the latest commits and re-runs `make setup` without losing data (volumes persist).

## What it does NOT do

- **Does not require root.** Everything runs as the invoking user. Docker socket access is the only privileged operation, and that's a Docker-level decision (group membership), not the script's.
- **Does not write outside the install directory.** No system files, no `/etc`, no `~/.config`.
- **Does not modify your shell profile** (`.bashrc`, `.zshrc`).
- **Does not collect telemetry.** No phone-home, no analytics ping.
- **Does not auto-start on boot.** If you want that, see [Production deployment](../deployment/production.md).
- **Does not configure SSL or a reverse proxy.** Local install only — `localhost:7461`.

## Trust but verify

Piping a remote script directly into `sh` is convenient but worth scrutinizing. Two ways to verify before running:

### Read the script first

```bash
curl -sSL https://appstore.cat/install.sh | less
```

The script is ~150 lines of POSIX shell. No obfuscation, no base64 blobs, no inline binaries.

### Read the source on GitHub

The script is checked into the repo at [`web/public/install.sh`](https://github.com/appstorecat/appstorecat/blob/master/web/public/install.sh). The `appstore.cat/install.sh` URL serves the same file built into the production Docker image — it cannot diverge from the source you can read on GitHub.

### Pin a version

The default script always pulls `master`. To pin a specific release:

```bash
git clone --branch v1.2.0 https://github.com/appstorecat/appstorecat.git
cd appstorecat
cp .env.development.example .env
make setup && make dev
```

This is also the recommended path for production-style installs where you want predictable behavior across machines.

## Customization

Two environment variables are honored:

| Var | Default | Purpose |
|---|---|---|
| `APPSTORECAT_REPO_URL` | `https://github.com/appstorecat/appstorecat.git` | Use a fork or mirror |
| `APPSTORECAT_DIR` | `appstorecat` | Install into a different directory name |

```bash
# Install from a fork into a custom directory
APPSTORECAT_REPO_URL=https://github.com/me/appstorecat-fork.git \
APPSTORECAT_DIR=my-appstorecat \
sh -c "$(curl -sSL https://appstore.cat/install.sh)"
```

## Troubleshooting

### "Docker daemon is not running"

Start Docker Desktop (macOS / Windows) or `sudo systemctl start docker` (Linux).

### "Port X is already in use"

The script warns but continues. To resolve, edit `.env` *before* re-running `make setup` and change the relevant `*_PORT` value, then re-run from inside the install directory:

```bash
cd appstorecat
make down
make setup && make dev
```

### `make setup` fails partway

Almost always one of:

- **Out of disk space** — check with `docker system df`. Prune unused images: `docker system prune -a`.
- **Network blocked** — composer (Packagist) and npm (registry.npmjs.org) must be reachable.
- **Docker daemon stopped during build** — restart Docker, then `cd appstorecat && make setup`.

Inspect logs:

```bash
cd appstorecat
docker compose logs
```

### Frontend never responds within timeout

Containers are running but slow to boot on cold caches. Check status:

```bash
cd appstorecat
make ps
make logs
```

If `appstorecat-server` keeps restarting, the most common cause is the database not being ready when migrations ran. Force a clean retry:

```bash
make down
make fresh
make dev
```

## Uninstall

```bash
cd appstorecat
make clean        # stops containers and removes volumes (WARNING: deletes your data)
cd ..
rm -rf appstorecat
```
