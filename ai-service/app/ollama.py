from typing import Any

import httpx


class OllamaError(Exception):
    """Ollama is down, timed out, or returned an error."""


class Ollama:
    """Thin client for the local Ollama server."""

    def __init__(self, client: httpx.AsyncClient, embedding_model: str, chat_model: str):
        self.client = client
        self.embedding_model = embedding_model
        self.chat_model = chat_model

    async def missing_models(self) -> list[str] | None:
        """The configured models that are not pulled yet, or None if Ollama is down."""
        try:
            response = await self.client.get("/api/tags", timeout=2)
            response.raise_for_status()
        except httpx.HTTPError:
            return None
        pulled = {model["name"] for model in response.json()["models"]}
        return [m for m in (self.embedding_model, self.chat_model) if m not in pulled]

    async def embed(self, texts: list[str]) -> list[list[float]]:
        response = await self._post("/api/embed", {"model": self.embedding_model, "input": texts})
        return response["embeddings"]

    async def chat(self, system: str, user: str, schema: dict[str, Any] | None = None) -> str:
        """One reply from the chat model. With `schema`, the reply is JSON matching it."""
        body: dict[str, Any] = {
            "model": self.chat_model,
            "stream": False,
            "options": {"temperature": 0},
            "messages": [
                {"role": "system", "content": system},
                {"role": "user", "content": user},
            ],
        }
        if schema:
            body["format"] = schema
        response = await self._post("/api/chat", body)
        return response["message"]["content"]

    async def _post(self, path: str, body: dict[str, Any]) -> dict[str, Any]:
        try:
            response = await self.client.post(path, json=body)
            response.raise_for_status()
        except httpx.HTTPError as error:
            raise OllamaError(str(error)) from error
        return response.json()
