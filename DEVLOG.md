# Dev Log — AI Support Ticketing System

A running diary of the project: what was done, why, what went wrong, and what's next.
Newest entry on top. Add an entry at the end of each work session.

---

## 2026-10-14 — SLA breach check (WBS 1.3.2.3)

### Done
- **Migration:** `tickets.first_response_breached_at` and `resolution_breached_at`. ERD updated.
- **`SlaTarget` enum** (`FirstResponse`, `Resolution`) maps each target to its due / met / breached columns.
- **`Ticket::slaBreached($target)` scope:** not resolved or closed, target not met, not escalated yet, deadline passed.
- **`tickets:check-sla` command:** for each organization (made current in turn), stamps the breach column and queues `EscalateTicket`. Scheduled every 5 minutes with `withoutOverlapping()`.
- **`EscalateTicket` job:** adds a system message ("First response SLA breached: it was due …") and emails the organization's admins and the assignee (`SlaBreached` notification).
- **Docker:** new `scheduler` service (`php artisan schedule:work`).
- **Ticket page** shows breach times in red next to the SLA dates.
- **Tests:** new `SlaBreachTest` (8: both targets, skips on-time / met / resolved / closed / already escalated, once only, every organization, reset on priority change, job message + recipients, schedule). Suite: 115 passing; Larastan clean.
- **Checked by hand** on the demo data: 63 breaches escalated, a second run escalated 0; the worker sent 104 emails, each to staff of the ticket's own organization (checked recipient org in the DB against the link host).
- WBS 1.3.2.3 marked done.

### Decisions
- **Stamp the breach column before dispatching:** a breach is escalated once, however often the command runs or if the worker is slow.
- **Loop over organizations in the command** rather than querying across tenants: tenant queries fail closed, and each queued job then carries its organization in Context.
- **A priority change resets the breach markers** (new deadlines), so a breach of the new deadline is escalated again.
- **SLA clocks don't pause** for pending / on hold, and use calendar time (business hours still open).
- **A job, not an event, for escalation** (as the WBS says); the bell (1.3.3) can add a database channel to `SlaBreached`.

### Problems & fixes
- A first check of "recipient org vs link" guessed the org from the email domain and showed 14 mismatches; the faker agents use random domains. Looking up each recipient in the database showed all 104 correct.

### Next
- [ ] Database notifications + bell (1.3.3): add the `database` channel to the notifications
- [ ] Horizon (1.3.2.1)
- [ ] Optionally pause SLA while pending / on hold

---

## 2026-10-14 — Events and queued listeners (WBS 1.3.1.2, 1.3.2.2)

### Done
- **Events** (`app/Events`): `TicketCreated`, `TicketReplied`, `TicketStatusChanged`, `TicketAssigned`. Dispatched by `CreateTicket`, `ReplyToTicket` and `UpdateTicket` (status and assignment only when they really change; unassigning dispatches nothing).
- **Listeners** (`app/Listeners`, auto-discovered):
  - `SendNewTicketNotification` (queued): emails staff except the requester.
  - `QueueTicketEmbedding` (sync): dispatches the `EmbedTicket` job.
  - `SendReplyNotification` (queued): emails the other side of a public reply.
  - `SendAssignmentNotification` (queued): emails the new assignee unless they assigned themselves.
  - `TicketStatusChanged` has no listener yet; the bell and SLA work will add them.
- **Notifications are no longer `ShouldQueue`:** the listener is the queued step.
- **Tests:** new `TicketEventsTest` (4: wiring, dispatch from each action, nothing on no-op updates, email listener queued). `TicketNotificationsTest` unchanged and still green, so behaviour is the same. Suite: 107 passing; Larastan clean.
- **Checked by hand:** a real ticket on acme → `SendNewTicketNotification` ran in the worker → 4 emails in Mailpit, only to acme staff (tenant context survives queued listeners), then `EmbedTicket` ran.
- WBS: 1.3.1.2 and 1.3.2.2 done; also 1.2.4.2 and 1.2.5.3 (done earlier, WBS was behind).

### Decisions
- **Actions announce, listeners react:** adding the bell, SLA escalation or AI triage means a new listener, not another line in an action.
- **Queue the listener, not the notification:** one hop on the queue instead of two.
- **`QueueTicketEmbedding` is sync:** it only dispatches a job that already has its own retries; queueing it would add a second hop.
- **`TicketAssigned` added** beyond the WBS's three events, because the assignment email already existed.

### Problems & fixes
- `Event::fake()` with no list also fakes Eloquent model events, so `BelongsToOrganization` never filled `organization_id` and the insert failed. → Fake only the ticket events.
- `Event::assertListening()` only exists after `Event::fake()`.

### Next
- [ ] SLA breach command + escalation job (1.3.2.3)
- [ ] Database notifications + bell (1.3.3), listening to these events
- [ ] Horizon (1.3.2.1)

---

## 2026-10-13 — File attachments

### Done
- **`attachments` table** (`organization_id`, `ticket_id`, nullable `message_id`, uploader, path, original name, MIME type, size), `Attachment` model (tenant-scoped, `path` hidden) and factory. ERD updated.
- **Upload** on the new ticket form and the reply form: up to 3 files, 5 MB each (images, PDF, text/CSV/log, Word, Excel, zip). `AddAttachments` action, called from `CreateTicket` and `ReplyToTicket`, stores files on the private `local` disk under `attachments/{organization_id}/`.
- **Download:** `GET /tickets/{reference}/attachments/{id}` (`scopeBindings()`, so the file must belong to that ticket). Same rule as the ticket page (`view` policy); files on internal notes are a 404 for customers.
- **Ticket page** lists the ticket's files under the description and each message's files under the message.
- **Tests:** new `TicketAttachmentsTest` (8), plus attachments in `TenancyTest` and a cross-organization download case in `TenantIsolationTest`. Suite: 97 passing; Larastan clean. Checked by hand: real upload through nginx and download with the original filename.
- WBS 1.2.3.3 marked done.

### Decisions
- **`ticket_id` + optional `message_id`, not polymorphic** (the ERD had suggested polymorphic): every file belongs to a ticket, so access is one question, "can you view this ticket?". Simpler queries and no `attachable_type` strings.
- **Private disk + download route**, never a public URL: the policy runs on every download.
- **3 × 5 MB** keeps a request under the 25 MB PHP (`post_max_size`) and nginx (`client_max_body_size`) limits.
- **The type is checked from the file's content** (Laravel's `File::types`), not only its extension.

### Problems & fixes
- `UploadedFile::fake()->image()` needs GD, which the PHP image doesn't have. → Tests use `fake()->create('x.png', …, 'image/png')`.
- Larastan read `Message::$type` as a string. → `@property MessageType $type`, like `Ticket`.
- `curl` on the host doesn't resolve `*.localhost`; use `--resolve acme.localhost:8088:127.0.0.1` for manual checks.

### Next
- [ ] Files stay on disk if a ticket is force-deleted (tickets are soft-deleted today); clean up when hard deletes arrive
- [ ] Flash messages; empty and loading states (WBS 1.2.4.2)

---

## 2026-10-13 — CI (GitHub Actions)

### Done
- **`.github/workflows/ci.yml`:** on every push to `main` and on pull requests, one job runs Pint (`--test`), Larastan, the frontend build, `tsc` and the PHPUnit tests against a `pgvector/pgvector:pg17` service (`ticketing_test`). First run green in about 70 s.
- **CI badge** in the README. WBS 1.2.5.1 and 1.2.5.2 marked done.

### Decisions
- **One job, steps in order:** simplest; the whole run is about a minute.
- **PHP runs directly on the runner (setup-php), not through Docker Compose:** faster, and only Postgres is needed (tests use array cache/session and the sync queue).
- **`WAYFINDER_COMMAND` env var in `vite.config.ts`:** locally Wayfinder runs through `docker compose exec`; CI sets it to `php artisan wayfinder:generate`.
- **Build before tests:** the Inertia tests need the Vite manifest.
- **`vp check` (frontend lint and format) is not in CI yet:** it reports formatting issues in 23 files, mostly starter-kit code and docs. Fix with `vp check --fix` and add it later.

### Next
- [ ] Rest of week 2: attachments, flash messages, empty and loading states
- [ ] Rest of week 3: events, SLA breach command, database notifications and bell, API, Horizon
- [ ] Optional: `vp check` in CI

---

## 2026-10-13 — WBS review and summary fix

### Done
- **Reviewed the code against the WBS** (`docs/Project - WBS.xlsx`, now in git). Status column updated: 25 of 98 tasks done (25%); week 2 at 47%, week 3 at 25%.
- **WBS:** two new columns per task, "Watch (YouTube search)" and "Read (docs / article)", for interview prep. Status cells are coloured (Done green, In progress yellow) with conditional formatting.
- **`docs/SUMMARY.md`:** AI section rewritten to match the WBS (Python FastAPI + Ollama, embeddings, RAG replies, auto-triage, provider layer with Claude off); "Next" follows the WBS.

### Decisions
- **Links are YouTube searches and official docs, not specific videos:** they can't go dead or point to the wrong video.
- **"Learn …" tasks are left for me to mark**; only code tasks were updated.

### Problems & fixes
- The earlier summary said "no Python" and "no triage", which contradicted the WBS. → Fixed; the WBS is the plan of record.
- Drift from the WBS: tests use PHPUnit (WBS says Pest); notifications are sent from actions instead of events and listeners.

### Next
- [ ] CI (GitHub Actions)
- [ ] Rest of week 2: attachments, flash messages, empty and loading states
- [ ] Rest of week 3: events, SLA breach command, database notifications and bell, API, Horizon

---

## 2026-10-13 — Week 2 review (WBS 1.2.5.3)

### Done
- **Week 2 (frontend), all build tasks done:** shared TypeScript types, ticket list with pagination / search / filters, create form with validation errors, detail page with thread and replies, file attachments, agent actions (assign, status, priority, internal notes), toasts and empty / loading states, feature tests for each action, GitHub Actions CI with a README badge.
- **Done early (from later weeks):** SLA due dates and recalculation, reopen on customer reply, queue worker, email notifications (Mailpit), Ollama with a local embedding model, and similar tickets (pgvector).
- **Checks green locally:** Pint, Larastan level 7 (no errors), 103 tests (387 assertions), `tsc`, `npm run build`. **CI green on GitHub** for the latest completed runs.
- **`docs/SUMMARY.md`** brought up to date (features, test count, CI, embeddings status, next steps).

### Decisions
- **Embeddings currently go Laravel → Ollama directly**, while the WBS plans Laravel → Python FastAPI → Ollama. To decide in week 4: route `EmbedTicket` through the Python service (only that job changes), or keep it in Laravel and adjust the plan.

### Problems & fixes
- None this review.

### Next
- [ ] Learning tasks still open in the WBS: TypeScript basics (1.2.1.1), Inertia basics (1.2.2.1)
- [ ] Week 3: events + queued listeners, SLA breach command, in-app notifications + bell, Horizon, public API (Sanctum)

---

## 2026-10-13 — Flash messages; empty and loading states (WBS 1.2.4.2)

### Done
- **Toasts** after ticket actions, using the starter kit's existing `Inertia::flash('toast', ...)` + Sonner setup: "Ticket TKT-1021 created.", "Reply sent.", "Internal note added.", "Ticket updated.".
- **Empty states:** the ticket list says "No tickets match these filters." (or "No tickets yet." with no filters); a ticket with no messages says "No messages yet.".
- **Loading states:** Filter, Create ticket and Send buttons show a spinner and are disabled while submitting; the status / priority / assignee dropdowns are disabled while their change saves.
- **Tests:** 2 more in `TicketPagesTest` (`assertInertiaFlash`, and no toast when validation fails). Suite: 103 passing; Larastan clean.

### Decisions
- **Reuse the starter kit's toast plumbing** (`useFlashToast` hook, `FlashToast` type) instead of adding a new flash prop.
- **Spinner inside the button**, as on the login page, so every form looks the same while busy.

### Next
- [ ] Week 2 review (WBS 1.2.5.3)
- [ ] Week 3: events + queued listeners, SLA breach command, in-app notifications + bell, Horizon

---

## 2026-10-13 — Similar tickets with local embeddings (ADR-002)

### Done
- **ADR-002 accepted:** free, self-hosted embeddings. Compared Voyage, OpenAI and Gemini (paid, or free tiers with catches) against local models; chose **Ollama + `qwen3-embedding:0.6b`** (1024 dims, 32K input).
- **Docker:** `ollama` service (`ollama/ollama:0.35.0`, model stored in the `ollama-data` volume).
- **Migration:** `tickets.embedding vector(1024)` with an HNSW cosine index, plus `tickets.embedding_model`.
- **`EmbedTicket` job** posts subject + description to Ollama's `/api/embed` and stores the vector. Queued by `CreateTicket` and by the seeder.
- **Ticket page:** "Similar tickets" (staff only): up to 5 tickets in the same organization with cosine similarity ≥ 0.5, closest first, via Laravel's `whereVectorSimilarTo()`. `embedding` is hidden from JSON.
- **Seeder:** 20 realistic tickets in same-topic pairs (login, password reset, double charge, refund, CSV export, slow pages, ...) instead of Lorem ipsum, so the demo shows real matches.
- **Tests:** new `SimilarTicketsTest` (4 tests: job queued, job stores vector, closest-first + other org excluded + embedding hidden, customers see none). `TestCase` now blocks stray HTTP (`Http::preventStrayRequests()`) and has `fakeEmbeddings()` / `vector()` helpers. Suite: 101 passing; Larastan clean.
- **Checked by hand:** the real job ran in the queue (≈1 s per ticket on CPU) and stored a 1024-dim vector.

### Decisions
- **No paid services:** local model, so no key, no limits, and ticket text never leaves the server. Cost: ≈1 GB RAM and ≈4.5 GB disk.
- **Laravel 13's built-in vector support** (`vector()` column, `vectorIndex()`, `AsVector` cast, `whereVectorSimilarTo()`): no extra package.
- **No `Embedder` interface:** one provider, and `Http::fake()` covers tests. Add the interface only if a second provider appears.
- **Staff only:** similar tickets can belong to other customers.
- **Threshold 0.5:** measured on the seed tickets with the real model: same-topic pairs scored at least 0.59 (avg 0.73), other pairs avg 0.37, highest 0.61.

### Problems & fixes
- `ollama/ollama:latest` (5.5 GB) was already on this machine, and pulling a pinned older tag started a second multi-GB download on a nearly full disk. Its digest matched `0.35.0`, so the service is pinned to `0.35.0` and reuses the local layers.
- The attachments work (`TicketAttachmentsTest`), committed in parallel, creates tickets too, so it failed once stray HTTP was blocked. Fixed with `$this->fakeEmbeddings()` in its `setUp()`.

### Next
- [ ] Re-embed when a ticket's subject or description changes (no edit flow yet)
- [ ] KB article suggestions (same approach, `kb_articles.embedding`)
- [ ] Business-hours SLA

---

## 2026-10-13 — Queue worker and email notifications

### Done
- **Docker:** `queue` service (same image as `app`, runs `php artisan queue:listen --tries=3`) and `mailpit` (inbox UI at http://localhost:8025). `.env` / `.env.example` now send mail over SMTP to `mailpit:1025`.
- **Notifications** (`app/Notifications`, all queued, mail only):
  - `TicketCreated`: a new ticket emails the organization's admins and agents (not the requester).
  - `TicketReplied`: a public reply emails the other side: a staff reply → the customer; a customer reply → the assigned agent. Internal notes email nobody.
  - `TicketAssigned`: emails the new assignee, unless they assigned it to themselves.
- **Links in emails:** `Organization::url()` and `Ticket::url()` build absolute URLs on the tenant subdomain (`http://acme.localhost:8088/tickets/TKT-1001`).
- **Tests:** new `TicketNotificationsTest` (6 tests). Suite: 86 passing; Larastan clean.
- **Checked by hand:** a notification queued in the `acme` context ran in the real worker, reached Mailpit, and linked to the acme subdomain.
- **App name:** `APP_NAME=ai-support-ticketing-system` (`.env`, `.env.example`), so emails (sender name, header, sign-off, footer) and the page title no longer say "Laravel".

### Decisions
- **`queue:listen`, not `queue:work`, in development:** slower per job, but it loads fresh code, so there's no stale worker after an edit. Production should use `queue:work` (plus a restart on deploy).
- **Notifications are sent from the action classes**, next to the change that causes them.
- **Tenant context reaches the job via Laravel Context**, as planned in ADR-001: the hidden organization id is serialized with the job and restored before the job's models load.
- **Emails need absolute tenant URLs:** a queued job has no request host, so `route()` alone would link to `localhost:8088` (no tenant, 404).

### Problems & fixes
- A `queue` service with only `image: ai-support-ticketing-app` worked here but depends on `app` having been built first; on a fresh clone Compose could try to pull it from a registry. Fixed by giving it the same `build:` block as `app` (cached, so no extra build time).
- After changing `APP_NAME`, queued emails stopped arriving. The Redis key prefix is built from `APP_NAME`, and the running `queue:listen` still had the old name (its worker processes inherit the parent's environment), so it watched the old queue. Fixed with `docker compose restart queue`; noted in `CLAUDE.md` and the README.

### Next
- [ ] First AI feature: similar tickets (provider/embedding ADR first)
- [ ] Business-hours SLA

---

## 2026-10-13 — Reopen a ticket when the customer replies

### Done
- **`ReplyToTicket`**: a customer reply on a pending, resolved or closed ticket sets it back to open, through `UpdateTicket`, so it is logged ("Status changed to open by …") and the status hook clears `resolved_at` / `closed_at`.
- **Tests:** 2 more in `TicketPagesTest`. Suite: 80 passing; Larastan clean.

### Decisions
- **Pending reopens too:** pending means "waiting on the customer", so their reply puts it back in the agents' queue.
- **On hold does not:** it means waiting on something else (a third party, a fix), which the customer's reply doesn't change.
- **Closed reopens as well** (no separate follow-up ticket). Simple; revisit if closed should be final.

### Next
- [ ] Email notifications (needs a queue worker)
- [ ] First AI feature: similar tickets (provider/embedding ADR first)
- [ ] Business-hours SLA

---

## 2026-10-13 — Search and filters on the ticket list

### Done
- **Ticket list filters:** search (reference or subject, case-insensitive), status, priority and assignee (a staff member, or "Unassigned"; staff only). A GET form keeps filters in the URL, and pagination keeps them (`withQueryString()`).
- **Tests:** 3 more in `TicketPagesTest`. Suite: 78 passing; Larastan clean.

### Decisions
- **Postgres `ilike` via `whereAny()`**, no search package; enough until AI similarity search arrives.
- **Unknown filter values are ignored** (`tryFrom`), not validation errors: a bad URL just shows the unfiltered list.
- **Filters stack on `visibleTo()`**, so customers searching still see only their own tickets.

### Next
- [ ] Reopen a ticket when the customer replies
- [ ] Email notifications (needs a queue worker)
- [ ] First AI feature: similar tickets (provider/embedding ADR first)
- [ ] Business-hours SLA

---

## 2026-10-12 — Recalculate SLA due dates on priority change

### Done
- **`Ticket::applySlaPolicy()`** picks the active SLA policy for the ticket's priority and sets `sla_policy_id` and both due dates from `created_at` (now, for a new ticket). No matching policy clears them.
- The `Ticket` `updating` hook calls it when the priority changes; `CreateTicket` calls it before saving.
- **Tests:** 1 more in `TicketPagesTest`. Suite: 75 passing; Larastan clean.

### Decisions
- **Due dates count from when the ticket was created**, not from the priority change, so escalating an old ticket can make it overdue straight away (that is the point of an SLA).
- **In the model hook, like the status timestamps**, so any future priority change (API, automation) gets it too.
- **Not applied on every create:** factories and the seeder set their own due dates.

### Next
- [ ] Business-hours SLA calculation
- [ ] Search and filters on the list
- [ ] Notifications; reopen on customer reply

---

## 2026-10-13 — Project summary

### Done
- **`docs/SUMMARY.md`:** one-page overview (stack, features, data model, planned AI features, key decisions, concepts, quality, next). Linked from the README.

### Decisions
- **AI features stay planned, not built:** similar tickets → KB suggestions → suggested replies, then summary and sentiment. Laravel calls the provider from queued jobs; pgvector does the search; no Python service.
- **Human in the loop:** AI drafts replies, agents send them.

### Next
- [ ] ADR for the AI provider and embedding model (sets the vector size)

---

## 2026-10-12 — Shared TypeScript types for tickets

### Done
- **`resources/js/types/tickets.ts`**: `Ticket`, `Message`, `Category`, `UserSummary` (`Pick<User, 'id' | 'name'>`), plus `TicketStatus`, `Priority` and `MessageType` as string unions matching the PHP enums. Exported from `@/types`.
- The tickets pages (`index`, `show`, `create`) use them instead of their own inline types.
- `tsc` and `npm run build` pass; ticket tests still green.

### Decisions
- **One `Ticket` type with optional relations** (`requester?`, `assignee?`, `category?`): pages load different relations, and the optional fields keep a single type honest about that.
- **`User` stays in `types/auth.ts`** (from the starter kit); relation fields use the smaller `UserSummary`, because controllers load only `id,name`.
- **Enum unions are written by hand.** They must be updated when a PHP enum changes; generating them is an option if they drift.

---

## 2026-10-12 — Ticket create flow with SLA due dates

### Done
- **New ticket page** (`tickets/create`, "New ticket" button on the list): subject, description, category, priority (default medium). The current user is the requester.
- **`CreateTicket` action** assigns the next reference (`TKT-1001`, `TKT-1002`, ...) per organization and picks the active SLA policy for the priority. It sets `first_response_due_at` / `resolution_due_at` from now; with no matching policy they stay empty.
- **Validation:** the category must belong to the current organization (`Rule::in` over the tenant-scoped categories).
- **Tests:** 4 more in `TicketPagesTest`. Suite: 74 passing; Larastan clean.

### Decisions
- **Reference = 1001 + count of the org's tickets (soft-deleted included).** Simple; the `(organization_id, reference)` unique index rejects the rare clash from two tickets created at the same moment.
- **Customers may pick a priority**; staff can still change it.
- **`business_hours_only` is ignored for now**: due dates count calendar minutes.

### Problems & fixes
- The Inertia test for the new page failed with "Unable to locate file in Vite manifest". Fixed with `npm run build` (this also regenerates the Wayfinder routes).

### Next
- [ ] Recalculate SLA due dates when the priority changes
- [ ] Business-hours SLA calculation
- [ ] Search and filters on the list
- [ ] Notifications; reopen on customer reply

---

## 2026-10-12 — Week review

### Done
- **Week 1 (2026-10-07 → 10-12):** Docker stack, ERD and migrations, single-DB multi-tenancy (ADR-001), login, roles and ticket policy, ticket list/detail, replies, status/priority/assignee changes, SLA timestamps, action classes, Pint + Larastan, two-org seed data.
- **Checks green:** 70 tests (196 assertions), Larastan level 7 no errors, Pint clean.
- **README** stub: stack, setup, demo logins, dev commands, links to docs.

### Next
- [ ] Ticket create flow (with SLA due dates)
- [ ] Search and filters on the list
- [ ] Notifications; reopen on customer reply
- [ ] Queue worker service, then the first AI feature (embeddings + similar tickets)

---

## 2026-10-12 — Seed two organizations

### Done
- **`DatabaseSeeder`** now seeds two orgs, `acme` and `globex`, through one `seedOrganization()` method. Each gets 1 admin, 3 agents, 5 customers, SLA policies, categories, 20 sample tickets with messages, and 6 KB articles.
- **Logins:** admin@/agent@/customer@example.com (acme) and admin@/agent@/customer@globex.example.com (globex), password `password`.

### Decisions
- **Same data shape for both orgs**, so tenant isolation can be checked by hand by logging into each subdomain.
- **Email domain per org**, because `users.email` is globally unique.

### Next
- (unchanged from the previous entry)

---

## 2026-10-12 — SLA timestamps; ticket actions

### Done
- **`first_responded_at`** is set on the first public reply by an agent or admin (internal notes and customer replies don't count).
- **`resolved_at` / `closed_at`** are set by a `Ticket` `updating` hook when the status changes: resolved stamps `resolved_at`; closed stamps `closed_at` (and `resolved_at` if missing); any other status clears both.
- **Ticket page** shows the due dates and recorded times when set.
- **Tests:** 2 more in `TicketPagesTest`. Suite: 70 passing; Larastan clean.
- **Action classes** `App\Actions\Tickets\UpdateTicket` and `ReplyToTicket` (`handle()`) hold the ticket logic; `TicketController` keeps authorization, validation and the response.

### Decisions
- **Status timestamps live in a model hook**, not the controller, so later status changes (e.g. reopen on customer reply) get them for free.
- **Actions take plain, validated data**, not the request, so jobs, commands or an API can reuse them.
- **Reopening clears `resolved_at`/`closed_at`**, so they always describe the latest resolution.

### Problems & fixes
- Larastan read `status` as a string (from the migration), so enum comparisons were "always false". Fixed with `@property TicketStatus $status` (and `Priority $priority`) on `Ticket`. The PHPDoc must sit above the `#[Fillable]` attribute to be picked up.

### Next
- [ ] Set SLA due dates when tickets are created (no create flow yet)
- [ ] Search and filters on the list (status, priority, assignee)
- [ ] Email notifications; reopen a ticket when the customer replies
- [ ] Admin: add users to the organization

---

## 2026-10-09 — Ticket assignee and priority

### Done
- **Priority and assignee dropdowns** next to status on the ticket page (admins and agents), saved on change. Each real change is logged ("Priority changed to urgent by Admin User.", "Assigned to Agent User by Admin User.", "Unassigned by …").
- **`TicketController::update()`** accepts any of `status`, `priority`, `assignee_id` (`sometimes`), so each dropdown sends only its own field.
- **`User::staff()` scope** (admins and agents), used for the assignee dropdown and to validate `assignee_id`.
- **Tests:** 4 more in `TicketPagesTest` (priority, assign and unassign, no customers, no other organization's agents). Suite: 68 passing; Larastan clean.

### Decisions
- **`assignee_id` is validated with `Rule::in(User::staff()->pluck('id'))`:** the staff query is tenant-scoped, so this one rule rejects customers *and* other organizations' users.
- **One `FieldSelect` helper** in the page instead of three copies of the dropdown.

### Next
- [ ] SLA timestamps (first response, resolved, closed)
- [ ] Search and filters on the list (status, priority, assignee)
- [ ] Email notifications; reopen a ticket when the customer replies
- [ ] Admin: add users to the organization

---

## 2026-10-08 — Pint and Larastan

### Done
- **Pint:** `pint.json` (`laravel` preset) and `composer lint`. The code already passed.
- **Larastan:** `larastan/larastan` (dev), `phpstan.neon` at **level 7** over `app`, `bootstrap/app.php`, `config`, `database` and `routes` (same as the React starter kit), and `composer analyse`. Result: no errors.

### Problems & fixes (10 findings)
- **The status message read `$ticket->status->value`** after the update, which Larastan couldn't type. → Uses the validated status string.
- **The trait's `creating` hook was typed as a plain `Model`** (no `organization_id`). → Typed as `self`.
- **The trait could assign `null` with no current organization.** → Throws `MissingOrganizationException` instead of relying on the `NOT NULL` error, which also gives a clearer message. `TenancyTest` updated.
- **`OrganizationScope` didn't declare its generic type.** → `@implements Scope<Model>`.
- **`CategoryFactory` used `words(2, true)`** (typed `array|string`). → A single fake word.
- **Laravel's default `config/filesystems.php` passed `env()` (mixed) to `rtrim()`.** → Cast to string.
- **`Organization::currentId()`** → documented as an unsigned id.
- **`composer require` hit GitHub's rate limit (HTTP 429)** while downloading PHPStan. → Retried a minute later.
- **Review of this commit:** setting a ticket to its current status still logged "Status changed…" → only logged when it actually changes (`wasChanged('status')`, with a test). The single-word category names could run out of unique values → back to two words. The trait's error now names the saved model's class.

---

## 2026-10-08 — Role permission tests (HTTP level)

### Done
- **`RolePermissionsTest`:** one data-driven test, run once per role, through real requests:

  | Role | Open someone else's ticket | Change status | Ticked "internal note" saved as |
  |---|---|---|---|
  | admin | 200 | allowed (302) | internal note |
  | agent | 200 | allowed (302) | internal note |
  | customer | 403 | 403 | normal reply |
- Suite: 63 passing.

### Decisions
- **Roles are tested at two levels:** `TicketPolicyTest` checks the policy rules directly, and `RolePermissionsTest` checks the pages. A new role or ability means adding a column to the table above.

### Problems & fixes
- **PHPUnit 12 warns when a data set has more values than the test method takes.** → One test per role that checks all three things.

---

## 2026-10-08 — Tenant isolation tests (HTTP level)

### Done
- **`TenantIsolationTest`:** an acme admin on acme's subdomain:
  - sees only acme's tickets in the list;
  - gets a 404 opening, replying to or updating a globex ticket, and globex's ticket is left unchanged;
  - opens acme's own ticket when both organizations use the same reference (`TKT-1000`).
- Suite: 60 passing.

### Decisions
- **Isolation is tested at two levels:** `TenancyTest` for every tenant model (scope, fail-closed, middleware), and `TenantIsolationTest` through real requests to the pages. New tenant pages should add a case here.

---

## 2026-10-08 — Ticket replies and status changes

### Done
- **Reply form** on the ticket page. Anyone who can view the ticket can reply. Admins and agents can tick "Internal note" (hidden from customers).
- **Status dropdown** for admins and agents, saved on change. Each change adds a system message ("Status changed to resolved by Agent User.").
- **Routes:** `POST /tickets/{ticket}/messages` (`reply`) and `PATCH /tickets/{ticket}` (`update`). Tickets now use route-model binding by reference (`{ticket:reference}`), still limited to the current organization.
- **Tests:** 7 more in `TicketPagesTest` (replies, internal notes, status changes, 403s, invalid status). Suite: 57 passing.

### Decisions
- **Still one controller** (`TicketController`: index, show, reply, update) with inline validation; no Form Request classes.
- **Internal notes are decided on the server:** a note is internal only if the box is ticked *and* the user passes the `update` rule, so a customer can't post one by sending the field.
- **Replies use the `view` rule** (if you can see a ticket you can reply); status changes use `update`.

### Next
- [ ] Change assignee and priority (same `update()` method)
- [ ] SLA timestamps (first response, resolved, closed)
- [ ] Email notifications; reopen a ticket when the customer replies
- [ ] Search and filters on the list
- [ ] Admin: add users to the organization

---

## 2026-10-08 — Roles and ticket policy

### Done
- **`TicketPolicy`** (auto-discovered): `view` (admins and agents: any ticket in the organization; customers: only their own), `update` (status, priority, assignee: admins and agents), `delete` (admins only).
- **The ticket detail page uses `Gate::authorize('view')`**, so a customer opening someone else's ticket now gets a 403 instead of a 404.
- **Tests:** `TicketPolicyTest` (each role against each ability). Suite: 50 passing.

### Decisions
- **Roles stay as they are:** one role per user (`roles` table, `$user->hasRole()`), seeded as admin, agent and customer. No permissions package; three roles don't need one.
- **The list still uses the `visibleTo()` scope:** policies decide about one ticket, scopes filter lists. Both say "customers only see their own".
- **`update` and `delete` are ready for the next features** (replies, status changes, assignment).

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
- [x] Role-based authorization (policies)
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
- **Fail closed:** with no current organization, scoped queries and creates throw `MissingOrganizationException`.
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
