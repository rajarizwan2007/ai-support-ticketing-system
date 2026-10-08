# Entity Relationship Diagram

Core data model for the AI Support Ticketing System. GitHub renders the diagram below automatically; a static copy is in [`erd.png`](erd.png).

```mermaid
erDiagram
    organizations ||--o{ users : "has members"
    organizations ||--o{ categories : owns
    organizations ||--o{ sla_policies : defines
    organizations ||--o{ tickets : owns
    organizations ||--o{ kb_articles : publishes

    roles ||--o{ users : "assigned to"

    users ||--o{ tickets : "requests"
    users |o--o{ tickets : "is assigned"
    users |o--o{ messages : writes
    users ||--o{ kb_articles : authors

    categories |o--o{ categories : "parent of"
    categories |o--o{ tickets : classifies
    categories |o--o{ kb_articles : groups

    sla_policies |o--o{ tickets : "applies to"

    tickets ||--o{ messages : contains

    organizations {
        bigint id PK
        string name
        string slug UK
        jsonb settings "timezone, business hours, AI options"
        timestamp created_at
        timestamp updated_at
    }

    roles {
        bigint id PK
        string name "Admin, Agent, Customer"
        string slug UK "admin, agent, customer"
        jsonb permissions
        timestamp created_at
        timestamp updated_at
    }

    users {
        bigint id PK
        bigint organization_id FK
        bigint role_id FK
        string name
        string email UK
        string password
        timestamp email_verified_at
        timestamp created_at
        timestamp updated_at
    }

    categories {
        bigint id PK
        bigint organization_id FK
        bigint parent_id FK "nullable, self-reference"
        string name
        string slug "unique per organization"
        text description
        timestamp created_at
        timestamp updated_at
    }

    sla_policies {
        bigint id PK
        bigint organization_id FK
        string name
        string priority "low, medium, high, urgent"
        int first_response_minutes
        int resolution_minutes
        boolean business_hours_only
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    tickets {
        bigint id PK
        bigint organization_id FK
        string reference "e.g. TKT-1042, unique per organization"
        bigint requester_id FK "users.id"
        bigint assignee_id FK "users.id, nullable"
        bigint category_id FK "nullable"
        bigint sla_policy_id FK "nullable"
        string subject
        text description
        string status "open, pending, on_hold, resolved, closed"
        string priority "low, medium, high, urgent"
        string channel "web, email, api"
        timestamp first_response_due_at
        timestamp resolution_due_at
        timestamp first_responded_at
        timestamp resolved_at
        timestamp closed_at
        text ai_summary
        string ai_sentiment "positive, neutral, negative"
        vector embedding "pgvector, for similar-ticket search"
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }

    messages {
        bigint id PK
        bigint ticket_id FK
        bigint user_id FK "nullable for system messages"
        string type "reply, internal_note, system"
        text body
        boolean is_ai_generated
        timestamp created_at
        timestamp updated_at
    }

    kb_articles {
        bigint id PK
        bigint organization_id FK
        bigint category_id FK "nullable"
        bigint author_id FK "users.id"
        string title
        string slug "unique per organization"
        text body
        string status "draft, published, archived"
        timestamp published_at
        int view_count
        vector embedding "pgvector, for article suggestions"
        timestamp created_at
        timestamp updated_at
    }
```

## Relationships

| Relationship | Type | Notes |
|---|---|---|
| organizations → users, categories, sla_policies, tickets, kb_articles | one-to-many | Every tenant-owned table has `organization_id`; all queries are scoped by it |
| roles → users | one-to-many | One role per user |
| users → tickets (`requester_id`) | one-to-many | The customer who opened the ticket |
| users → tickets (`assignee_id`) | one-to-many, optional | The agent working on it; null = unassigned |
| tickets → messages | one-to-many | The conversation thread |
| users → messages | one-to-many, optional | Null author = system message (e.g. "status changed") |
| categories → categories (`parent_id`) | self-referencing | Lets categories nest, e.g. Billing → Refunds |
| categories → tickets, kb_articles | one-to-many, optional | Shared taxonomy, so a ticket's category can point to matching articles |
| sla_policies → tickets | one-to-many, optional | Chosen by priority when the ticket is created |
| users → kb_articles (`author_id`) | one-to-many | The agent or admin who wrote it |

## Design decisions

- **Multi-tenant by `organization_id`.** One database, with every tenant-owned row tagged by organization. This is simpler than a database per tenant, and Laravel global scopes make it easy to enforce.
- **Roles are global** (Admin, Agent, Customer) with one role per user. If roles need to be per-organization or permissions more detailed, `spatie/laravel-permission` can replace this table.
- **Customers are users too.** They have the Customer role, so requesters and agents share one table, and messages always point to `users`.
- **SLA deadlines are stored on the ticket** (`first_response_due_at`, `resolution_due_at`). They're calculated once from the policy when the ticket is created, so later policy changes don't silently move existing deadlines, and breach checks are simple date comparisons.
- **Status, priority, channel and similar fields are strings** backed by PHP enums. That's easier to change than Postgres enum types.
- **`embedding` columns use pgvector.** The size (e.g. `vector(1024)`) depends on the embedding model, which isn't chosen yet. These columns power similar-ticket search and "suggested KB articles".
- **Tickets are soft-deleted** (`deleted_at`), so history and SLA reporting survive deletion.
- **`messages.type = internal_note`** keeps agent-only notes in the same thread. They're filtered out of what customers see.

## Possible later additions

- `attachments` (polymorphic, for tickets and messages)
- `kb_article_ticket` pivot: which articles were suggested or used to resolve a ticket
- `tags` with a `ticket_tag` pivot
- `ticket_events` audit log (status, assignee and priority changes)
