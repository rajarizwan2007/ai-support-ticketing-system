# AI Support Ticketing System

[![CI](https://github.com/rajarizwan2007/ai-support-ticketing-system/actions/workflows/ci.yml/badge.svg)](https://github.com/rajarizwan2007/ai-support-ticketing-system/actions/workflows/ci.yml)

A multi-tenant support ticketing app (Laravel 13, Inertia + React). Work in progress; AI features (auto-triage, RAG suggested replies via a Python service with a local model) are planned.

## Stack

PHP 8.4 · Laravel 13 · PostgreSQL 17 + pgvector · Redis 7 · Ollama (local embeddings) · Inertia/React/TypeScript · Docker Compose

## Getting started

```bash
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec ollama ollama pull qwen3-embedding:0.6b   # local embedding model, once
docker compose exec app php artisan migrate:fresh --seed
npm install && npm run dev
```

Demo logins (password `password`):

| Organization | URL | Users |
|---|---|---|
| Acme | http://acme.localhost:8088 | admin@ / agent@ / customer@example.com |
| Globex | http://globex.localhost:8088 | admin@ / agent@ / customer@globex.example.com |

Emails are sent by the `queue` container and caught by Mailpit: http://localhost:8025 (restart it with `docker compose restart queue` after changing `.env`).

## Development

```bash
docker compose exec app php artisan test   # tests (ticketing_test database)
docker compose exec app composer lint      # Pint
docker compose exec app composer analyse   # Larastan, level 7
```

GitHub Actions runs Pint, Larastan, the frontend build, the type-check and the tests on every push to `main` and on pull requests (`.github/workflows/ci.yml`).

## Docs

- [Project summary](docs/SUMMARY.md)
- [ERD](docs/erd.md)
- API docs (Scribe): http://localhost:8088/docs/ — regenerate with `docker compose exec app php artisan scribe:generate`
- [ADR-001: multi-tenancy](docs/adr/0001-multi-tenancy.md)
- [ADR-002: embedding model for similar tickets](docs/adr/0002-ai-providers.md)
- [Dev log](DEVLOG.md)
