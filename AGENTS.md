# Repository Guidelines

## Project Structure & Module Organization

This repository defines a distributed YOURLS deployment.

- `docker-compose.yml` declares Nginx, three YOURLS replicas, Redis, MySQL, networks, volumes, and health checks.
- `nginx/nginx.conf` configures the reverse proxy/load balancer.
- `mysql/my.cnf` stores MySQL configuration.
- `yourls/Dockerfile` builds the custom image with PHP Redis support.
- `yourls/redis-sessions.ini` enables Redis-backed PHP sessions.
- `yourls/docker-entrypoint-custom.sh` wraps the upstream YOURLS entrypoint.
- `yourls/plugins/redis-cache/plugin.php` implements the Redis cache plugin.

There is no dedicated `tests/` directory or asset pipeline.

## Build, Test, and Development Commands

- `docker compose config` validates the Compose file and interpolated environment values.
- `docker compose build` builds the custom YOURLS image from `yourls/Dockerfile`.
- `docker compose up -d` starts the full local stack in detached mode.
- `docker compose ps` checks service status and health checks.
- `docker compose logs -f nginx yourls1 redis mysql` follows key runtime logs.
- `docker compose down` stops containers while preserving named volumes.
- `docker compose down -v` removes containers and volumes; use only for data resets.

Create a local `.env` before running the stack. It must provide `YOURLS_DB_USER`, `YOURLS_DB_PASS`, `MYSQL_ROOT_PASSWORD`, and required upstream YOURLS variables.

## Coding Style & Naming Conventions

Use two-space indentation for YAML and INI files. Use compact Bash with `set -e` for startup scripts. For PHP plugins, follow YOURLS hook patterns and prefix local functions, as in `cluster_redis_init`.

Prefer Compose service names (`mysql`, `redis`, `yourls1`) over hard-coded IP addresses. Keep comments short and operational.

## Testing Guidelines

No automated test suite is defined. Before committing, run `docker compose config`, rebuild with `docker compose build`, start with `docker compose up -d`, and confirm checks with `docker compose ps`.

For plugin changes, verify a cache miss and a repeated lookup against Redis-backed cache behavior. Check `yourls1`, `redis`, and `mysql` logs for startup or connection errors.

## Commit & Pull Request Guidelines

Recent history uses short subject lines, often Portuguese, with prefixes such as `feat:`. Continue that pattern for feature work, for example `feat: adiciona configuração de sessões Redis`.

Pull requests should include a summary, validation commands, required `.env` changes, and notes about data or volume reset requirements. Include screenshots only for user-facing YOURLS or admin UI changes.

## Security & Configuration Tips

Do not commit `.env`, database dumps, generated credentials, or local volume data. Keep secrets in environment variables. When changing exposed ports, database credentials, or Redis session settings, document the operational impact.
