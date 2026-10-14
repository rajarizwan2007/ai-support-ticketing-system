from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    """Read from environment variables (OLLAMA_URL, EMBEDDING_MODEL, CHAT_MODEL, ...)."""

    ollama_url: str = "http://ollama:11434"
    embedding_model: str = "qwen3-embedding:0.6b"
    chat_model: str = "gemma3:1b"  # see docs/adr/0003-chat-model.md
    ollama_timeout: float = 120.0


settings = Settings()
