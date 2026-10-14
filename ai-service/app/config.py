from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    """Read from environment variables (OLLAMA_URL, EMBEDDING_MODEL, ...)."""

    ollama_url: str = "http://ollama:11434"
    embedding_model: str = "qwen3-embedding:0.6b"
    ollama_timeout: float = 120.0


settings = Settings()
