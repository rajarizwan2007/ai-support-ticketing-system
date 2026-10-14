from collections.abc import AsyncIterator
from contextlib import asynccontextmanager
from typing import Annotated

import httpx
from fastapi import Depends, FastAPI, HTTPException, Request
from pydantic import BaseModel, Field

from app.config import settings
from app.ollama import Ollama, OllamaError


@asynccontextmanager
async def lifespan(app: FastAPI) -> AsyncIterator[None]:
    """One HTTP connection pool to Ollama for the whole app."""
    async with httpx.AsyncClient(
        base_url=settings.ollama_url, timeout=settings.ollama_timeout
    ) as client:
        app.state.ollama = Ollama(client, settings.embedding_model)
        yield


app = FastAPI(title="Ticketing AI service", lifespan=lifespan)


def get_ollama(request: Request) -> Ollama:
    return request.app.state.ollama


OllamaDep = Annotated[Ollama, Depends(get_ollama)]


class Health(BaseModel):
    status: str
    ollama: bool


class EmbedRequest(BaseModel):
    texts: list[Annotated[str, Field(min_length=1)]] = Field(min_length=1, max_length=32)


class EmbedResponse(BaseModel):
    model: str
    embeddings: list[list[float]]


@app.get("/health")
async def health(ollama: OllamaDep) -> Health:
    """The service is up; `ollama` says whether the model server answers."""
    return Health(status="ok", ollama=await ollama.is_up())


@app.post("/embed")
async def embed(body: EmbedRequest, ollama: OllamaDep) -> EmbedResponse:
    """One vector per text, from the local embedding model."""
    try:
        embeddings = await ollama.embed(body.texts)
    except OllamaError as error:
        raise HTTPException(status_code=503, detail="Embedding model unavailable") from error
    return EmbedResponse(model=ollama.embedding_model, embeddings=embeddings)
