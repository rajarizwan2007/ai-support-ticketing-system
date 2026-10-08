# Dev Log — AI Support Ticketing System

A running diary of the project: what was done, why, what went wrong, and what's next.
Newest entry on top. Add an entry at the end of each work session.

---

## 2026-10-08 — Tickets list and detail pages

### Done
- **`/tickets`:** table (reference, subject, status, priority, requester, assignee, created), newest first, 20 per page with Previous/Next.
- **`/tickets/{reference}`:** ticket details and the message thread. Internal notes are highlighted, and hidden from customers.
- **"Tickets"** in the sidebar and header.
- **Tests:** `TicketPagesTest` (agent sees all, customer sees own, customer gets a 404 on someone else's ticket, customer sees no internal notes). Suite: 47 passing.

### Decisions
- **One visibility rule, in one place:** `Ticket::visibleTo($user)` (customers see only the tickets they requested). Both pages use it, so a policy can wait for the roles work.
- **Kept it minimal:** one controller (`TicketController`), models passed straight to Inertia (no API Resources), labels formatted in the page, plain Tailwind table with the kit's `Badge`.

### Next
- [ ] Reply to a ticket; change status and assignee
- [ ] Search and filters on the list
- [ ] Role-based authorization (policies)
- [ ] Admin: add users to the organization

---

## 2026-10-08 — Login (React starter kit)

### Done
- **Ported the Laravel React starter kit** (Inertia + React + TypeScript + shadcn/ui, Fortify for auth, Wayfinder for typed routes). First stripped it with the kit's own `install:features` tool, keeping only: login/logout, forgot/reset password, dashboard, and profile/password/appearance settings.
- **Every web route is a tenant route:** `ResolveOrganization` is in the `web` group. `acme.localhost:8088` works; the bare `localhost:8088` is a 404.
- **The organization name is shared with React** (`HandleInertiaRequests`) and shown in the sidebar logo.
- **Tests:** the kit's auth and settings tests run on a tenant subdomain via `TestCase::inOrganization()`, plus a test that a user can't log in on another organization's subdomain. Suite: 43 passing.

### Decisions
- **No public registration, 2FA, passkeys, email verification or account deletion.** Users belong to an organization (admins will add them), and customers with tickets can't be deleted anyway.
- **No custom login code:** Fortify finds users through the tenant-scoped `User` model, so only that organization's users can log in.
- **Node runs on the host** (`npm run dev` / `npm run build`); the Wayfinder Vite plugin runs `php artisan` through `docker compose exec`.

### Problems & fixes
- **The kit's composer hook ran `install:features` with all features on.** → Re-ran it from a clean copy with `composer install --no-scripts`.
- **The Wayfinder plugin called the host's PHP** (wrong version). → Pointed its `command` at the container.
- **Review fixes:** a mixed-case email saved in profile settings locked the user out (Fortify lowercases at login, Postgres compares case-sensitively). → The profile request lowercases the email. Also removed the kit's GitHub/docs links from the sidebar and header, and added `: void` to the kit's test methods.
- **Deferred from the review:** cross-tenant "email already taken" in profile settings, `/user/confirm-password` 500 (no page uses it), logging out other devices on password change, trimming the user data sent to the browser.
- **TypeScript errors from `Route::redirect()`:** Laravel 13.35 lists a `query` HTTP method that Wayfinder's types don't know about. → Used plain `Route::get()` redirects.

### Next
- [ ] Tickets list and ticket detail pages (agents and customers)
- [ ] Role-based authorization (policies)
- [ ] Admin: add users to the organization
- [ ] Re-render `docs/erd.png`

---

## 2026-10-08 — Tenancy code

### Done
- **`messages.organization_id`** (indexed, cascade on delete) added to the create-messages migration; like other models it's filled from the current organization. ERD updated (`docs/erd.png` is now stale).
- **`Organization::makeCurrent()` / `current()` / `currentId()` / `forgetCurrent()`:** the current tenant id lives in hidden Laravel Context.
- **`BelongsToOrganization` trait** (`app/Models/Concerns`) on User, Category, SlaPolicy, Ticket, Message and KbArticle: adds `OrganizationScope` and fills `organization_id` on create.
- **Fail closed:** with no current organization, scoped queries throw `MissingOrganizationException`, and creates fail on the `NOT NULL` column.
- **`ResolveOrganization` middleware** (alias `organization`): subdomain → organization. Unknown, missing or nested subdomains get a 404. Not attached to any route yet; tenant routes will use it once auth exists.
- **`ExistsInCurrentOrganization` validation rule** for foreign keys from requests.
- **Factories** default `organization_id` to the current organization, so tests call `makeCurrent()` once and every factory follows. The seeder makes `acme` current.
- **Tests:** `TenancyTest` (leak and fail-closed checks for every tenant model, middleware cases, the rule). Suite: 26 passing.

### Decisions
- **Hidden Context, not a static property or container singleton, holds the tenant.** It's per request, kept out of logs, and Laravel serializes it into queued jobs and restores it (flushing the old value first) before each job runs. So jobs keep their tenant, and a long-running worker can't leak one job's tenant into the next.
- **The middleware runs before auth and route-model binding** (`prependToPriorityList` before `AuthenticatesRequests`), because both run tenant-scoped queries.
- **Users are tenant-scoped too, so no 403 check is needed.** A session from another tenant's subdomain loads no user and the request is a guest. That's safe and needs no extra code.
- **Keep it simple:** the smallest code that works. Complexity only where there's no other way.
- **Edited the existing messages migration** instead of adding a new one: no production data yet, and dev is reset with `migrate:fresh --seed`.
- **An organization's relations only work while it is current.** `$globex->tickets` returns nothing while acme is current, and throws with no tenant. That's consistent with failing closed, but admin or billing code that loops over organizations must make each one current (or bypass the scope deliberately). Noted on the `Organization` class.

### Problems & fixes (from a code review of the tenancy commit)
- **A `Message` hook that copied the ticket's organization** (added for queued jobs) let a request write a message into another tenant's ticket. → Removed. Jobs already restore their tenant via Context, so messages simply use the current organization.
- **Validation rule accepted a JSON `true` as id 1.** → Only integers or digit strings pass.
- **The seeder's `WithoutModelEvents` disabled the tenancy hooks.** → Removed.
- **A first round of fixes was too complex** (session peeking for a 403, memoisation, an overridable hook, constructor guards). → Reverted to the simple version above.

### Next
- [x] Authentication (login per subdomain), with the `organization` middleware on the whole `web` group
- [ ] Role-based authorization (policies)
- [ ] Tenant-prefixed cache keys once caching is used
- [ ] Ticket reference generation (per-organization sequence)
- [ ] Re-render `docs/erd.png`

---

## 2026-10-08 — ADR-001: multi-tenancy

### Done
- Wrote `docs/adr/0001-multi-tenancy.md`, the first Architecture Decision Record. It covers the single-database decision, the rejected options, trade-offs, safeguards and when to revisit.
- Linked it from `CLAUDE.md`, `AGENTS.md`, `docs/erd.md` and the ERD entry below.
- Revised it after a code review: fail-closed scope, user-vs-subdomain check, same-tenant validation of foreign keys, tenant-prefixed cache keys, email-enumeration and vector-search caveats, local `acme.localhost` setup.

### Decisions
- **Big decisions are recorded as ADRs** in `docs/adr/` (numbered, dated; once settled, never rewritten; a new ADR supersedes an old one). Implementation progress lives here in the dev log, not in the ADR.

### Problems & fixes
- **The review found that `messages` has no `organization_id`**, although the ADR says every tenant-owned table does. → The ADR keeps the rule; the column is added in the tenancy task.

### Next
- [x] Add `organization_id` (indexed) to `messages`, plus factory and seeder updates
- [x] Tenancy middleware (subdomain → organization) and fail-closed `BelongsToOrganization` trait
- [x] Same-tenant validation for foreign keys from requests, and cross-tenant leak tests

---

## 2026-10-08 — Migrations, models, factories and test database

### Done
- **Migrations** for organizations, roles, users (adds `organization_id`, `role_id`), categories, sla_policies, tickets, messages and kb_articles, all with `bigint` IDs.
- **Models** with typed relationships and casts. Enums in `app/Enums`: TicketStatus, Priority, TicketChannel, Sentiment, MessageType, KbArticleStatus.
- **Factories** for every model, with states such as `agent()`, `assigned()`, `resolved()`, `published()`, `internalNote()`, `system()` and `childOf()`. Related records always share one organization.
- **Seeders:** `RoleSeeder` (admin, agent, customer). `DatabaseSeeder` builds a demo org "Acme Inc" (slug `acme`): 1 admin, 3 agents, 5 customers, 4 SLA policies, 4 categories (Refunds nested under Billing), 20 tickets with messages, and 6 KB articles. Logins are `admin@`, `agent@` and `customer@example.com`, all with password `password`.
- **Tests:** `tests/Feature/DataModelTest.php` (8 passing) covers tenant-consistent factories, enum casts and delete rules.

### Decisions
- **Delete rules:**
  - Organization deleted → everything it owns is deleted (cascade).
  - Agent deleted → their tickets become unassigned; their messages keep the thread with a null author.
  - Customer with tickets → can't be deleted (deactivate instead).
  - Category or SLA policy deleted → tickets keep going with a null value.
- **Requester, KB author and role keys use Postgres's default "no action" rule, not RESTRICT.** RESTRICT is checked immediately, which would break deleting a whole organization. No action is checked at the end of the statement, after the cascade has removed the tickets too.
- **Indexes:** Postgres does *not* auto-index foreign keys, so every `organization_id` is indexed, either directly or as the first column of a composite or unique index (`organization_id + slug`, `organization_id + reference`, `organization_id + status`). Messages have `ticket_id + created_at` for loading threads.
- **`organization_id` and `role_id` are not mass-assignable**, so a request can't move a user to another tenant or promote them to admin.
- **`embedding` columns are deferred** until the embedding model is chosen, because the vector size depends on it.

### Problems & fixes
- **Factory state created a stray organization:** `assigned()` read `$attributes['organization_id']` while it was still an unresolved factory. → Wrap the value in a closure (`fn (array $attributes) => ...`) so it's resolved after the organization exists.
- **SLA factory `priority()` state overrode `->for($org)`**, because states run after parent relationships. → States no longer set `organization_id`.
- **⚠️ Tests wiped the dev database (twice).** Docker sets `DB_*` as real env vars, which Laravel reads from `$_SERVER` first, and phpunit's `<env force="true">` doesn't touch `$_SERVER`.
  → Added `<server ... force="true">` entries pointing at a separate `ticketing_test` database (created by `docker/postgres/init/02-test-database.sql`).
  → Added a guard in `tests/TestCase.php` that refuses to run unless the database name ends in `_test`. It lives in `refreshApplication()`: a guard in `beforeRefreshingDatabase()` is silently overridden by the `RefreshDatabase` trait's own method, since trait methods beat inherited ones.
- **Postgres container saw an empty init folder:** the bind mount pointed at a stale directory (different inode). → `docker compose up -d --force-recreate postgres`.
- **Re-running a rolled-back migration failed:** `add_organization_and_role_to_users_table` can't add a NOT NULL column to a table that still has users. That's fine for now (`migrate:fresh`), but once there's production data such changes need staging: add nullable → backfill → add the constraint.

### Next
- [ ] Tenancy: resolve the organization from the subdomain (middleware) and add a `BelongsToOrganization` trait with a global scope and auto-fill on create
- [ ] Ticket reference generation (per-organization sequence)
- [ ] Authentication and role-based authorization (policies)
- [ ] Choose the embedding model → add `embedding vector(N)` columns and HNSW indexes

---

## 2026-10-08 — Database design (ERD)

### Done
- Designed the core data model: organizations, users, roles, tickets, messages, categories, SLA policies and KB articles.
- `docs/erd.md`: Mermaid ER diagram (GitHub renders it), relationship table and design notes. `docs/erd.png`: static render.

### Decisions
- **Multi-tenancy: single database + single codebase (final, see [ADR-001](docs/adr/0001-multi-tenancy.md)).** Tenant resolved from the subdomain; every tenant-owned table has `organization_id`.
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
