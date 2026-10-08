# Dev Log — AI Support Ticketing System

A running diary of the project: what was done, why, what went wrong, and what's next.
Newest entry on top. Add an entry at the end of each work session.

---

## 2026-10-08 — Database design (ERD)

### Done
- Designed the core data model: organizations, users, roles, tickets, messages, categories, SLA policies and KB articles.
- `docs/erd.md`: Mermaid ER diagram (GitHub renders it), relationship table and design notes. `docs/erd.png`: static render.

### Decisions
- **Multi-tenancy: single database + single codebase (final).** Tenant resolved from the subdomain; every tenant-owned table has `organization_id`.
  Chosen over database-per-tenant for lower ops cost, simple migrations, easy cross-tenant reporting and a single pgvector index.
  Safeguards: a global scope/trait (never manual `where`), an index on `organization_id`, cross-tenant leak tests, and optionally Postgres row-level security.
- **One global role per user** (Admin / Agent / Customer). Customers are users too, so requester, assignee and message author all point to `users`.
- **SLA deadlines are stored on the ticket** when it's created, so policy changes don't move existing deadlines.
- **Status, priority and channel are strings backed by PHP enums**, not Postgres enums, so they're easier to change.
- **pgvector `embedding`** on tickets and KB articles, for similar-ticket search and article suggestions. The dimension waits on the choice of embedding model.
- **Internal notes are `messages.type = internal_note`**, kept in the same thread.

### Next
- [ ] Choose the embedding model (sets the vector dimension)
- [ ] Write migrations, models, enums and factories from the ERD
- [ ] Decide on attachments, tags and the ticket event log

---

## 2026-10-07 — Laravel Boost (AI-assisted development)

### Done
- Installed `laravel/boost` (v2, dev dependency) and ran `php artisan boost:install --guidelines --skills --mcp`.
- Boost generated:
  - **Guidelines**: version-aware Laravel conventions in `CLAUDE.md` (inside `<laravel-boost-guidelines>`)
  - **Skills** in `.claude/skills/`: laravel-best-practices, testing-best-practices, tailwindcss-development, infer-conventions, deploying-to-cloud
  - **MCP server** config in `.mcp.json` (`boost:mcp`), which gives AI agents 10 tools: application-info, database-schema, database-query, database-connections, read-log-entries, last-error, browser-logs, search-docs, get-absolute-url, record-rule
  - `boost.json`: which Boost features are enabled
- Tested the MCP server: it starts inside the container and lists all 10 tools.

### Decisions
- **Why Boost:** AI coding agents often suggest outdated Laravel code. Boost gives them current, version-specific guidelines and docs search, plus live access to *this* app (DB schema, logs, routes, errors) through MCP. It's Laravel's official answer to AI-assisted development, and good to know hands-on.
- `AGENTS.md` is kept as a copy of `CLAUDE.md`, so non-Claude agents (Codex, Cursor, etc.) get the same guidelines.

### Problems & fixes
- The generated `.mcp.json` ran `php artisan boost:mcp` on the host, where PHP isn't installed.
  → Changed it to `docker compose exec -T app php artisan boost:mcp` (`-T` = no TTY, required for MCP's stdin/stdout protocol).
- ⚠️ `boost:update` / `boost:install` may regenerate `.mcp.json`. Re-apply the Docker command if that happens.

### Next
- [ ] Run the `infer-conventions` skill once there's real app code
- [ ] Interview notes: Boost = guidelines + skills + MCP tools; makes AI agents "Laravel-aware" and "app-aware"

---

## 2026-10-07 — Project setup: Docker + Laravel

### Done
- Created a Docker Compose stack (`docker-compose.yml`):
  - `app` — PHP 8.4-FPM (custom image: `docker/php/Dockerfile`) with pdo_pgsql, phpredis, intl, zip, bcmath, opcache, pcntl + Composer 2
  - `nginx` — nginx 1.27, serves `public/`, forwards PHP to `app:9000`
  - `postgres` — `pgvector/pgvector:pg17`; `docker/postgres/init/01-extensions.sql` enables the `vector` extension
  - `redis` — Redis 7 with append-only persistence
- Installed Laravel 13 into the project root.
- Switched Laravel from SQLite to PostgreSQL, and cache / session / queue from database to Redis (`.env`, `.env.example`).
- Ran the default migrations: 9 tables in the `ticketing` database.
- Verified: the welcome page loads at http://localhost:8088, `artisan db:show` reports pgsql, the Redis ping succeeds, and a cache write/read through Redis works. pgvector is at 0.8.7.

### Decisions
- **nginx added** alongside PHP: PHP-FPM can't serve HTTP by itself.
- **Ports:** app `8088`, Postgres `5433`, Redis `6380`. 8080/8000/8081 were already taken on the host, and the non-default DB/Redis ports avoid clashing with any locally installed services. All are overridable in `.env` (`APP_PORT`, `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`).
- **PHP container runs as host UID/GID (1000)**, so files created by artisan/composer stay editable on the host.
- **Redis for cache, sessions and queue**: fast, and it's what we'll want for queued AI jobs later.
- **pgvector instead of a separate vector DB**: embeddings live next to the ticket data in Postgres, which keeps the stack simple.

### Problems & fixes
- `composer create-project laravel/laravel .` failed with "Project directory is not empty" because the Docker files, README and `.git` were already there.
  → Created the project in `/tmp/laravel` inside the container, then `cp -rn` into the project (no overwrites).
- Port 8080 was already allocated on the host → moved the app to 8088.
- `php artisan tinker` warned "Writing to directory /var/www/.config/psysh is not allowed" because the container user's home folder wasn't writable.
  → The Dockerfile now creates `/var/www/.config` and `/var/www/.composer` owned by `www-data`.
- In tinker, `Redis::connection()` failed because `Redis` resolved to the phpredis class, not Laravel's facade.
  → Use `Illuminate\Support\Facades\Redis` explicitly in tinker.
- `UID=$(id -u) docker compose ...` fails in bash (`UID: readonly variable`).
  → Not needed: UID/GID are set in `.env`.

### Next
- [ ] Authentication (decide: Breeze / starter kit / API-only?)
- [ ] Ticket domain: `tickets`, `ticket_messages`, statuses, priorities, assignees
- [ ] Node/Vite container (or local Node) for frontend assets
- [ ] Queue worker service in `docker-compose.yml` (needed once AI jobs exist)
- [ ] AI features: embeddings for tickets (pgvector), similar-ticket search, auto-categorisation and reply suggestions
