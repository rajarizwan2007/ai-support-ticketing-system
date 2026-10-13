# AI Support Ticketing System

A multi-tenant support ticketing app (Laravel 13, Inertia + React). Work in progress; AI features (embeddings, similar tickets, reply suggestions) are planned.

## Stack

PHP 8.4 · Laravel 13 · PostgreSQL 17 + pgvector · Redis 7 · Inertia/React/TypeScript · Docker Compose

## Getting started

```bash
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed
npm install && npm run dev
```

Demo logins (password `password`):

| Organization | URL | Users |
|---|---|---|
| Acme | http://acme.localhost:8088 | admin@ / agent@ / customer@example.com |
| Globex | http://globex.localhost:8088 | admin@ / agent@ / customer@globex.example.com |

## Development

```bash
docker compose exec app php artisan test   # tests (ticketing_test database)
docker compose exec app composer lint      # Pint
docker compose exec app composer analyse   # Larastan, level 7
```

## Docs

- [Project summary](docs/SUMMARY.md)
- [ERD](docs/erd.md)
- [ADR-001: multi-tenancy](docs/adr/0001-multi-tenancy.md)
- [Dev log](DEVLOG.md)
