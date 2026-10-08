# ADR-001: Multi-tenancy approach

- **Status:** Accepted
- **Date:** 2026-10-08

## Context

Many organizations (tenants) share one support system. Each must only ever see its own tickets, users and knowledge base. We want strong isolation without heavy operations work, and the AI features (pgvector similarity search over tickets and KB articles) work best with one shared index.

## Decision

**One PostgreSQL database, one shared schema, one codebase.**

- The tenant is identified by subdomain (`acme.<domain>` → organization with slug `acme`).
- Every tenant-owned table has an indexed `organization_id`.
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
- `users.email` is unique across the whole platform, so one email can't belong to two organizations.

## Safeguards

- `BelongsToOrganization` trait: a global scope plus `organization_id` filled in automatically on create.
- Middleware that resolves the tenant from the subdomain.
- `organization_id` is not mass-assignable, so a request can't move a record to another tenant.
- Cross-tenant leak tests.
- Optional later backstop: Postgres row-level security (RLS).

## Implementation status

- **Done** (`514f8fc`): `organization_id` columns and indexes, tenant-consistent factories, delete rules, mass-assignment protection.
- **To do:** subdomain middleware, `BelongsToOrganization` trait, leak tests.

## When to revisit

If an enterprise or regulatory requirement demands physical isolation, or one tenant dominates the load, move that tenant to its own database and record it in a new ADR that supersedes this one.
