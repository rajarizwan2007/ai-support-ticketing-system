# ADR-003: Local chat model

- **Status:** Accepted
- **Date:** 2026-10-14

## Context

The next AI features (auto-triage, suggested replies) need a text-generation ("chat") model. ADR-002 chose a free, local setup (Ollama) for embeddings and left the chat model as a separate decision.

**Constraints**

- No paid services, and ticket text stays on the server (as in ADR-002).
- **Memory:** this dev machine has 16 GB of RAM, with about 2 GB free while the stack runs. The chat model is loaded alongside the embedding model (about 0.7 GB).
- **Answers must be JSON** that matches a schema (category, priority), so Laravel can store them without parsing free text.

## Options considered

Sizes from Ollama's library pages, checked on 2026-10-14. Larger models (4B and up: `gemma3:4b` 3.4 GB, `qwen3.5:4b` 3.3 GB) were ruled out on memory alone.

| Model          | Download | Context |
| -------------- | -------- | ------- |
| `gemma3:1b`    | 806 MB   | 32K     |
| `qwen3.5:0.8b` | 1.3 GB   | 256K    |
| `qwen3:1.7b`   | 1.4 GB   | 40K     |

**Smoke test:** 12 hand-labeled tickets, 4 categories (Account, Billing, Refunds, Technical) and 4 priorities. Each model got the same system prompt, temperature 0, and Ollama's structured output (`format` = JSON schema). Script: `ai-service/scripts/compare_chat_models.py`. CPU only.

| Model           | Valid JSON | Category  | Priority | Avg / slowest | RAM loaded |
| --------------- | ---------- | --------- | -------- | ------------- | ---------- |
| **`gemma3:1b`** | 12/12      | **12/12** | 5/12     | 2.1 s / 3.0 s | 0.88 GB    |
| `qwen3.5:0.8b`  | 12/12      | 10/12     | 5/12     | 3.0 s / 9.6 s | 0.56 GB    |
| `qwen3:1.7b`    | 12/12      | 11/12     | 5/12     | 1.6 s / 2.3 s | 1.88 GB    |

## Decision

- **`gemma3:1b`** as the chat model, set by `CHAT_MODEL` in the AI service (`OLLAMA_CHAT_MODEL` in `.env`).
- The best category accuracy in the test, steady response times, and it fits in memory next to the embedding model.
- Requests use **temperature 0** and a **JSON schema** (`format`), so triage answers are always valid JSON with allowed values.

## Consequences

**Positive**

- Free, private, works offline; about 0.9 GB of RAM.
- Schema-constrained output: 12/12 valid answers, no free-text parsing.

**Negative**

- **Priority is unreliable:** every model scored 5/12. AI-suggested priority must be shown as a suggestion for an agent to confirm, never applied silently (the human-in-the-loop plan in the WBS).
- A 1B model writes short, generic replies. Suggested replies will lean on the KB articles retrieved by RAG, and an agent edits them before sending.
- 12 tickets is a smoke test, not an evaluation. The week-6 evaluation (~100 labeled tickets, confusion matrix) can overturn this choice.
- The model must be downloaded once per machine: `docker compose exec ollama ollama pull gemma3:1b`.

## When to revisit

- After the week-6 evaluation, or if triage accuracy on real tickets is poor.
- On a machine with more free RAM: try a 4B model (`gemma3:4b`, `qwen3.5:4b`).
- The week-7 Claude decision compares this model against Claude Haiku on the same evaluation.

## Sources

- Ollama library, gemma3 tags: https://ollama.com/library/gemma3/tags
- Ollama library, qwen3.5 tags: https://ollama.com/library/qwen3.5/tags
- Ollama library, qwen3 tags: https://ollama.com/library/qwen3/tags
- Ollama structured outputs: https://docs.ollama.com/capabilities/structured-outputs
