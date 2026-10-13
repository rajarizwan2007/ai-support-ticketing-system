# Project Summary

A multi-tenant support ticketing app: organizations get their own subdomain, customers raise tickets, agents and admins work them against SLA deadlines. AI features (embeddings, similar tickets, reply suggestions) come next.

## Stack

| Layer | Choice | Why |
|---|---|---|
| Backend | PHP 8.4, Laravel 13 | |
| Frontend | Inertia + React + TypeScript (Laravel starter kit, Fortify, Wayfinder) | One app, no separate API; typed routes |
| Database | PostgreSQL 17 + pgvector | Embeddings live next to the data; no separate vector DB |
| Cache / sessions / queue | Redis 7 | Fast; needed for queued AI jobs |
| Runtime | Docker Compose (app, queue, nginx, postgres, redis, mailpit) | Same setup everywhere; PHP never installed on the host |

## Features so far

- **Login** per organization subdomain (no public registration).
- **Roles:** admin, agent, customer (one role per user).
- **Tickets:** list (paginated, with search and filters), detail with message thread, create, reply, internal notes (staff only), change status / priority / assignee. Every change is logged as a system message. A customer reply reopens a pending, resolved or closed ticket.
- **References** `TKT-1001`, `TKT-1002`, … per organization.
- **SLA:** due dates set on create from the matching policy and recalculated when the priority changes; `first_responded_at`, `resolved_at`, `closed_at` recorded automatically.
- **Email notifications** (queued): new ticket → staff; public reply → the customer or the assigned agent; assignment → the new assignee. Mailpit catches them in development.
- **Demo data:** two orgs, `acme` and `globex`, with users, tickets and KB articles.

## Data model

`organizations` → `users` (with `roles`), `categories` (nested), `sla_policies`, `tickets`, `messages`, `kb_articles`. All IDs are `bigint`. See [ERD](erd.md).

## AI features (planned)

None are built yet. The schema already has room for them.

| Feature | Prepared in schema | Order |
|---|---|---|
| Similar tickets (semantic search) | `tickets.embedding` (pgvector, in ERD) | 1st |
| KB article suggestions | `kb_articles.embedding`, `kb_article_ticket` pivot (in ERD) | 2nd |
| Suggested replies (agent reviews before sending) | `messages.is_ai_generated` | 3rd |
| Ticket summary | `tickets.ai_summary` | Later |
| Sentiment (positive / neutral / negative) | `tickets.ai_sentiment`, `Sentiment` enum | Later |

- **How it will run:** Laravel calls the AI provider's API from queued jobs (Redis); pgvector does similarity search in Postgres. No Python service.
- **Human in the loop:** AI drafts replies; agents send them. No fully automatic replies.
- **Not planned:** category classification, priority prediction, customer chatbot.
- **Open choices:** AI provider and embedding model (sets the vector size; the `embedding` migrations wait for it). Record them in an ADR.
- **Claude today:** used only as a coding assistant (Claude Code with Laravel Boost), not inside the app.

## Key decisions

| Decision | Reason / trade-off |
|---|---|
| **Single-database multi-tenancy** ([ADR-001](adr/0001-multi-tenancy.md)) | Simple ops, one migration path, one vector index. Cost: isolation lives in code, so one missed scope leaks data. |
| **`BelongsToOrganization` trait + global scope** | Every tenant query is scoped automatically; no manual `where`. |
| **Fail closed** | No current tenant → queries throw, never return all tenants' rows. |
| **Tenant in hidden Laravel Context** | Per request, kept out of logs, carried into queued jobs. |
| **Customers are users** | Requester, assignee and author all point to `users`. |
| **SLA deadlines stored on the ticket** | Changing a policy doesn't move existing deadlines. |
| **Enums as strings + PHP enums** (not Postgres enums) | Easier to change. |
| **Policies for one record, scopes for lists** | `TicketPolicy` decides access; `Ticket::visibleTo()` filters lists. |
| **Action classes** (`CreateTicket`, `ReplyToTicket`, `UpdateTicket`) | Logic reusable from jobs, commands or an API; controllers only authorize, validate, respond. |
| **Status timestamps in a model hook** | Any status change (incl. future reopen) stamps them for free. |
| **No permissions package** | Three roles don't need one. |
| **Keep it simple** | Smallest code that works; complexity only where unavoidable. |

## Concepts worth knowing

- **ADRs** (`docs/adr/`): short, dated records of one big decision (context, decision, options, consequences). Never rewritten; a new ADR supersedes an old one. Day-to-day progress goes in `DEVLOG.md`.
- **Factories** (`database/factories/`): build valid models in one line, e.g. `Ticket::factory()->assigned()->create()`. Used for tests and seeding, never production. They keep tests short and keep rules (like "related records share one organization") in one place.
- **Laravel Boost:** MCP server that gives AI coding agents version-correct Laravel docs plus live access to this app (schema, routes, logs).

## Quality

- **Tests:** PHPUnit, 86 passing. Tenant isolation and role permissions are tested at two levels (model/policy and real HTTP requests).
- **Test DB guard:** tests run on `ticketing_test`; `tests/TestCase.php` refuses any database not ending in `_test`.
- **Static analysis:** Larastan level 7, no errors. **Style:** Pint.

## Next

- Business-hours SLA
- First AI feature (embeddings + similar tickets)
