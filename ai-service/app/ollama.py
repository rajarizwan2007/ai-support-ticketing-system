import httpx


class OllamaError(Exception):
    """Ollama is down, timed out, or returned an error."""


class Ollama:
    """Thin client for the local Ollama server."""

    def __init__(self, client: httpx.AsyncClient, embedding_model: str):
        self.client = client
        self.embedding_model = embedding_model

    async def is_up(self) -> bool:
        try:
            response = await self.client.get("/api/version", timeout=2)
        except httpx.HTTPError:
            return False
        return response.is_success

    async def embed(self, texts: list[str]) -> list[list[float]]:
        try:
            response = await self.client.post(
                "/api/embed", json={"model": self.embedding_model, "input": texts}
            )
            response.raise_for_status()
        except httpx.HTTPError as error:
            raise OllamaError(str(error)) from error
        return response.json()["embeddings"]
