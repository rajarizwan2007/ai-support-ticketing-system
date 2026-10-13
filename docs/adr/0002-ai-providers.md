# ADR-002: Embedding model for similar tickets

- **Status:** Accepted
- **Date:** 2026-10-13

## Context

The first AI feature is **similar tickets**: embed each ticket's subject and description, store the vector in Postgres (pgvector), and show agents the closest tickets in the same organization. The model fixes the vector size, so the `embedding` column waits for this decision.

**Constraint: no paid services.** The project must not depend on a paid API or on a provider's free allowance that can change or run out.

Anthropic does not offer an embedding model (its docs point to Voyage AI), so embeddings come from another source either way.

## Options considered

Prices and limits checked on the providers' own pages on 2026-10-13.

| Option | Cost | Why not chosen |
|---|---|---|
| **Self-hosted open model with Ollama** | Free (local CPU and RAM) | **Chosen** |
| Voyage `voyage-4` / `voyage-4-lite` (API) | $0.06 / $0.02 per 1M tokens after 200M free tokens; no card needed for the free tier | Free only within a one-off allowance; low rate limits without a card; ticket text leaves the machine |
| OpenAI `text-embedding-3-small` (API) | $0.02 per 1M tokens, no free tier | Paid |
| Google Gemini embeddings (free tier) | Free with daily limits | Free-tier content may be used to improve Google's products, which rules out customer tickets; limits have changed often |

Local models compared (all run in Ollama):

| Model | Size | Vector size | Max input | Notes |
|---|---|---|---|---|
| **`qwen3-embedding:0.6b`** | ≈ 640 MB | 1024 | 32K tokens | Multilingual, strong for its size; same 1024 size as Voyage |
| `bge-m3` | ≈ 1.2 GB | 1024 | 8K tokens | Strong multilingual alternative |
| `embeddinggemma` | ≈ 300M params | 768 | 2K tokens | Smallest; short input limit |
| `nomic-embed-text` | ≈ 270 MB | 768 | short (sources disagree) | Popular, but long tickets may be cut off |

## Decision

- **Ollama in Docker** (`ollama` service, image pinned to `ollama/ollama:0.35.0`) running **`qwen3-embedding:0.6b`**.
- **Column `tickets.embedding vector(1024)`**, HNSW index with cosine distance (`$table->vectorIndex()`), plus `tickets.embedding_model` so a later model change can find stale vectors.
- **Queued job `EmbedTicket`** calls Ollama's `/api/embed` with the subject and description; it runs after a ticket is created (and for seed data).
- **Laravel's built-in vector support** (`AsVector` cast, `whereVectorSimilarTo()`); no new Composer package.
- **Tests never call Ollama:** `Http::preventStrayRequests()` in `TestCase`, and `fakeEmbeddings()` returns fixed vectors.
- **Only staff see similar tickets:** they can list other customers' tickets.
- **Text generation later** (summaries, reply drafts) is a separate decision with its own ADR.

## Consequences

**Positive**
- No cost, no account, no API key, no rate limits.
- Ticket text never leaves the server.
- Works offline and in CI (tests use fakes).

**Negative**
- Uses about 1 GB of RAM while running and about 4.5 GB of disk (Docker image plus model).
- Slower than a hosted API on CPU; fine because embedding runs in the queue.
- The model must be downloaded once per machine: `docker compose exec ollama ollama pull qwen3-embedding:0.6b`.
- Changing the model later means re-embedding every ticket (vectors from different models can't be compared).
- The similarity threshold (0.5) is a starting guess; tune it on real tickets.

## When to revisit

- If quality is poor on real tickets: try `bge-m3`, or a larger Qwen3 embedding model if the server has the RAM.
- If the app moves to a host without enough RAM for Ollama: reconsider a hosted API (and accept the cost).

## Sources

- Anthropic, Embeddings guide: https://platform.claude.com/docs/en/build-with-claude/embeddings
- Voyage AI pricing and free tier (MongoDB docs): https://www.mongodb.com/docs/voyageai/management/billing/
- OpenAI pricing: https://developers.openai.com/api/docs/pricing
- Qwen3 Embedding: https://huggingface.co/Qwen/Qwen3-Embedding-8B-GGUF
