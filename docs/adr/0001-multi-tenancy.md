# ADR-001: Multi-tenancy approach

- **Status:** Accepted
- **Date:** 2026-10-08

## Context

Many organizations (tenants) share one support system. Each must only ever see its own tickets, users and knowledge base. We want strong isolation without heavy operations work, and the AI features (pgvector similarity search over tickets and KB articles) work best with one shared index.

## Decision

**One PostgreSQL database, one shared schema, one codebase.**

- The tenant is identified by subdomain (`acme.<domain>` → organization with slug `acme`). Locally this is `acme.localhost:8088` (`*.localhost` resolves to 127.0.0.1 in browsers); there is no "default tenant" fallback.
- Every tenant-owned table has an indexed `organization_id`, including child tables such as `messages`, so each can be scoped (and later searched) on its own.
- Queries are scoped automatically by a global scope, never by manual `where` clauses.

## Options considered

| Option | Why rejected |
|---|---|
| Database per tenant | Migrations run N times, more connections and backups, cross-tenant reporting is hard, and every tenant needs its own vector index. Too much ops work for this project. |
| Schema per tenant (Postgres schemas) | Same migration and reporting pain, and the tooling is awkward in Laravel. |

## Consequences

**Positive**
- Low cost and simple operations: one database to run, back up and monitor.
- One migration path for all tenants.
- Cross-tenant reporting and admin are easy.
- One pgvector index for all AI search.

**Negative**
- Isolation is enforced in code: one missed scope is a data leak.
- One heavy tenant can slow down the others ("noisy neighbour").
- Restoring or exporting a single tenant is harder.
- `users.email` is unique across the whole platform, so one email can't belong to two organizations, and "email already taken" reveals that an account exists with another tenant. Registration and invite errors must use a generic message.
- A shared approximate-nearest-neighbour index (HNSW) applies the `organization_id` filter after the index scan, so a small tenant can get few or no results. Use pgvector's iterative index scans (`hnsw.iterative_scan`), or a partial index for very large tenants.

## Safeguards

- **`BelongsToOrganization` trait:** a global scope plus `organization_id` filled in automatically on create.
- **Fail closed:** if no tenant is set (queue jobs, Artisan commands, scheduled tasks), scoped queries throw an exception instead of returning every tenant's rows. Cross-tenant work must opt out explicitly (e.g. `withoutGlobalScope`), and queued jobs carry the organization id and set it before running.
- **Subdomain middleware:** resolves the organization from the subdomain. Users are tenant-scoped, so a session from another organization finds no user and is treated as a guest.
- **Same-tenant references:** every foreign key that comes from user input (`assignee_id`, `requester_id`, `category_id`, `sla_policy_id`, `parent_id`, `author_id`) is validated to belong to the current organization.
- **`organization_id` is not mass-assignable**, so a request can't move a record to another tenant.
- **Tenant-prefixed cache keys**, so cached data is never shared between tenants.
- **Cross-tenant leak tests** for every tenant-owned model and endpoint.
- **Optional later backstop:** Postgres row-level security (RLS).

## When to revisit

If an enterprise or regulatory requirement demands physical isolation, or one tenant dominates the load, move that tenant to its own database and record it in a new ADR that supersedes this one.
