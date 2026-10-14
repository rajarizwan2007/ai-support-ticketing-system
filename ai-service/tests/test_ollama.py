import json

import httpx
import pytest

from app.ollama import Ollama, OllamaError


def ollama_answering(handler) -> Ollama:
    client = httpx.AsyncClient(base_url="http://ollama", transport=httpx.MockTransport(handler))
    return Ollama(client, "embed-model", "chat-model")


@pytest.mark.anyio
async def test_chat_sends_the_messages_and_schema_and_returns_the_reply():
    sent = {}

    def handler(request):
        sent["path"] = request.url.path
        sent["json"] = json.loads(request.content)
        return httpx.Response(200, json={"message": {"content": '{"category": "Billing"}'}})

    schema = {"type": "object", "properties": {"category": {"type": "string"}}}

    reply = await ollama_answering(handler).chat("You triage tickets.", "Charged twice", schema)

    assert reply == '{"category": "Billing"}'
    assert sent["path"] == "/api/chat"
    assert sent["json"]["model"] == "chat-model"
    assert sent["json"]["format"] == schema
    assert sent["json"]["options"] == {"temperature": 0}
    assert sent["json"]["messages"] == [
        {"role": "system", "content": "You triage tickets."},
        {"role": "user", "content": "Charged twice"},
    ]


@pytest.mark.anyio
async def test_chat_without_a_schema_sends_no_format():
    sent = {}

    def handler(request):
        sent["json"] = json.loads(request.content)
        return httpx.Response(200, json={"message": {"content": "Hello"}})

    assert await ollama_answering(handler).chat("Be brief.", "Hi") == "Hello"
    assert "format" not in sent["json"]


@pytest.mark.anyio
async def test_chat_raises_when_ollama_fails():
    with pytest.raises(OllamaError):
        await ollama_answering(lambda request: httpx.Response(500)).chat("system", "user")
