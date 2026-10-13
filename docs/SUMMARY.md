# Project Summary

A multi-tenant support ticketing app: organizations get their own subdomain, customers raise tickets, agents and admins work them against SLA deadlines. AI features (auto-triage, RAG suggested replies) come later, through a Python service with a local model.

## Stack

| Layer | Choice | Why |
|---|---|---|
| Backend | PHP 8.4, Laravel 13 | |
| Frontend | Inertia + React + TypeScript (Laravel starter kit, Fortify, Wayfinder) | One app, no separate API; typed routes |
| Database | PostgreSQL 17 + pgvector | Embeddings live next to the data; no separate vector DB |
| Cache / sessions / queue | Redis 7 | Fast; needed for queued AI jobs |
| AI (planned) | Python FastAPI + Ollama (local models) | Learn Python; free, private AI; Claude optional behind the same interface |
| Runtime | Docker Compose (app, queue, nginx, postgres, redis, mailpit, ollama) | Same setup everywhere; PHP never installed on the host |

## Features so far

- **Login** per organization subdomain (no public registration).
- **Roles:** admin, agent, customer (one role per user).
- **Tickets:** list (paginated, with search and filters), detail with message thread, create, reply, internal notes (staff only), change status / priority / assignee. Every change is logged as a system message. A customer reply reopens a pending, resolved or closed ticket.
- **References** `TKT-1001`, `TKT-1002`, … per organization.
- **SLA:** due dates set on create from the matching policy and recalculated when the priority changes; `first_responded_at`, `resolved_at`, `closed_at` recorded automatically.
- **File attachments** on tickets and replies (local storage; files on internal notes hidden from customers).
- **Feedback in the UI:** success toasts after every ticket action, empty states, spinners and disabled controls while saving.
- **Email notifications** (queued): new ticket → staff; public reply → the customer or the assigned agent; assignment → the new assignee. Mailpit catches them in development.
- **Similar tickets** (staff only): each ticket is embedded by a local model (Ollama, `qwen3-embedding:0.6b`) in a queued job; the ticket page lists the 5 closest tickets in the organization (pgvector, cosine, HNSW index). Free, and ticket text never leaves the server.
- **Demo data:** two orgs, `acme` and `globex`, with users, tickets and KB articles.

## Data model

`organizations` → `users` (with `roles`), `categories` (nested), `sla_policies`, `tickets`, `messages`, `kb_articles`. All IDs are `bigint`. See [ERD](erd.md).

## AI features (planned, WBS weeks 4–6)

Similar tickets is built (see [ADR-002](adr/0002-ai-providers.md)); the rest are planned.

**Architecture:** Laravel ↔ a **Python FastAPI service** ↔ a **local model (Ollama)** for chat and embeddings. Laravel calls the service from queued jobs; vectors are stored and searched in Postgres with pgvector.

| Feature | How | Prepared in schema |
|---|---|---|
| Embeddings | **Built for tickets:** a queued Laravel job (`EmbedTicket`) calls Ollama directly and stores `tickets.embedding`. Routing it through the Python `/embed` service is decided in week 4. | `tickets.embedding` (done), `kb_articles.embedding` (pending) |
| RAG suggested replies | pgvector finds the top KB chunks; Python `/suggest-reply` returns a draft with its sources | `messages.is_ai_generated`, `kb_article_ticket` pivot |
| Auto-triage | Python `/triage` returns category, priority, confidence (JSON); runs on ticket created | `category_id`, `priority` |
| Summary, sentiment | Not in the WBS yet | `tickets.ai_summary`, `tickets.ai_sentiment` |

- **Human in the loop:** agents accept, edit or reject suggestions; low-confidence triage goes to manual review.
- **Provider layer:** one interface, two providers: Ollama (default) and Claude (off). Versioned prompts, timeouts, retries, prompt-injection guard (ticket text treated as data).
- **Quality (week 6):** ~100 labeled tickets, accuracy per category with a confusion matrix, token/latency logging, fallback to a manual queue when the AI is down.
- **Claude decision (week 7):** whether to enable the Claude API, decided by running the same evals on Claude Haiku vs the local model.
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

- **Tests:** PHPUnit, 103 passing. Tests never call real HTTP services (`Http::preventStrayRequests()`). Tenant isolation and role permissions are tested at two levels (model/policy and real HTTP requests).
- **Test DB guard:** tests run on `ticketing_test`; `tests/TestCase.php` refuses any database not ending in `_test`.
- **Static analysis:** Larastan level 7, no errors. **Style:** Pint.
- **CI:** GitHub Actions runs Pint, Larastan, the frontend build, the type check and the tests on every push.

## Next (from the WBS)

- Week 3: events, SLA breach command, database notifications and bell, public API (Sanctum), Horizon
- Week 4: Python + FastAPI + Ollama, then the AI features
- Business-hours SLA (not in the WBS)
