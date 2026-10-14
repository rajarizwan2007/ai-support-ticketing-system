from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
from typing import Annotated

import httpx
from fastapi import Depends, FastAPI, HTTPException, Request
from pydantic import BaseModel, Field

from app import prompts
from app.config import settings
from app.ollama import Ollama, OllamaError


@asynccontextmanager
async def lifespan(app: FastAPI) -> AsyncIterator[None]:
    """One HTTP connection pool to Ollama for the whole app."""
    async with httpx.AsyncClient(
        base_url=settings.ollama_url, timeout=settings.ollama_timeout
    ) as client:
        app.state.ollama = Ollama(client, settings.embedding_model, settings.chat_model)
        yield


app = FastAPI(title="Ticketing AI service", lifespan=lifespan)


def get_ollama(request: Request) -> Ollama:
    return request.app.state.ollama


OllamaDep = Annotated[Ollama, Depends(get_ollama)]


class Health(BaseModel):
    status: str
    ollama: bool
    missing_models: list[str]


class EmbedRequest(BaseModel):
    texts: list[Annotated[str, Field(min_length=1)]] = Field(min_length=1, max_length=32)


class EmbedResponse(BaseModel):
    model: str
    embeddings: list[list[float]]


@app.get("/health")
async def health(ollama: OllamaDep) -> Health:
    """The service is up; `ollama` says whether the model server answers, and
    `missing_models` lists configured models that still need `ollama pull`."""
    missing = await ollama.missing_models()
    return Health(status="ok", ollama=missing is not None, missing_models=missing or [])


@app.post("/embed")
async def embed(body: EmbedRequest, ollama: OllamaDep) -> EmbedResponse:
    """One vector per text, from the local embedding model."""
    try:
        embeddings = await ollama.embed(body.texts)
    except OllamaError as error:
        raise HTTPException(status_code=503, detail="Embedding model unavailable") from error
    return EmbedResponse(model=ollama.embedding_model, embeddings=embeddings)


class Article(BaseModel):
    id: int
    title: Annotated[str, Field(min_length=1)]
    body: Annotated[str, Field(min_length=1)]


class SuggestReplyRequest(BaseModel):
    subject: Annotated[str, Field(min_length=1)]
    description: Annotated[str, Field(min_length=1)]
    articles: list[Article] = Field(default=[], max_length=5)


class SuggestReplyResponse(BaseModel):
    reply: str
    source_ids: list[int]
    model: str
    prompt: str


@app.post("/suggest-reply")
async def suggest_reply(body: SuggestReplyRequest, ollama: OllamaDep) -> SuggestReplyResponse:
    """A draft reply grounded in the given KB articles. The caller picks the articles
    (by similarity), so they are the sources; a 1B model can't cite reliably itself.
    An agent reviews and edits the draft before anything is sent."""
    user = prompts.suggest_reply_user(
        body.subject, body.description, [(a.id, a.title, a.body) for a in body.articles]
    )
    try:
        content = await ollama.chat(prompts.SUGGEST_REPLY_SYSTEM, user)
    except OllamaError as error:
        raise HTTPException(status_code=503, detail="Chat model unavailable") from error

    reply = prompts.clean_reply(content)
    if not reply:
        raise HTTPException(status_code=502, detail="Chat model returned an empty reply")

    return SuggestReplyResponse(
        reply=reply,
        source_ids=[article.id for article in body.articles],
        model=ollama.chat_model,
        prompt=prompts.SUGGEST_REPLY_VERSION,
    )
