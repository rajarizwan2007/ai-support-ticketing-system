import json

import httpx
import pytest
from fastapi.testclient import TestClient

from app.main import app, get_ollama
from app.ollama import Ollama

TAGS = {"models": [{"name": "embed-model"}, {"name": "chat-model"}]}


def fake_ollama(handler) -> Ollama:
    """An Ollama client whose HTTP calls are answered by `handler`, never the network."""
    client = httpx.AsyncClient(base_url="http://ollama", transport=httpx.MockTransport(handler))
    return Ollama(client, "embed-model", "chat-model")


@pytest.fixture
def client():
    with TestClient(app) as test_client:
        yield test_client
    app.dependency_overrides.clear()


def use(handler) -> None:
    app.dependency_overrides[get_ollama] = lambda: fake_ollama(handler)


@pytest.mark.parametrize(
    ("tags", "missing"),
    [
        (TAGS, []),
        ({"models": [{"name": "embed-model"}]}, ["chat-model"]),
        ({"models": []}, ["embed-model", "chat-model"]),
    ],
)
def test_health_lists_models_that_are_not_pulled(client, tags, missing):
    use(lambda request: httpx.Response(200, json=tags))

    response = client.get("/health")

    assert response.status_code == 200
    assert response.json() == {"status": "ok", "ollama": True, "missing_models": missing}


def test_health_is_ok_when_ollama_errors_or_is_unreachable(client):
    def unreachable(request):
        raise httpx.ConnectError("refused")

    for handler in (lambda request: httpx.Response(500), unreachable):
        use(handler)
        assert client.get("/health").json() == {
            "status": "ok",
            "ollama": False,
            "missing_models": [],
        }


def test_embed_returns_one_vector_per_text(client):
    sent = {}

    def handler(request):
        sent["json"] = json.loads(request.content)
        return httpx.Response(200, json={"embeddings": [[0.1, 0.2], [0.3, 0.4]]})

    use(handler)

    response = client.post("/embed", json={"texts": ["printer broken", "login fails"]})

    assert response.status_code == 200
    assert response.json() == {"model": "embed-model", "embeddings": [[0.1, 0.2], [0.3, 0.4]]}
    assert sent["json"] == {"model": "embed-model", "input": ["printer broken", "login fails"]}


@pytest.mark.parametrize("body", [{"texts": []}, {"texts": [""]}, {}, {"texts": ["x"] * 33}])
def test_embed_rejects_invalid_input(client, body):
    use(lambda request: httpx.Response(200, json={"embeddings": []}))

    assert client.post("/embed", json=body).status_code == 422


def test_embed_returns_503_when_ollama_fails(client):
    use(lambda request: httpx.Response(500))

    response = client.post("/embed", json={"texts": ["hello"]})

    assert response.status_code == 503
    assert response.json() == {"detail": "Embedding model unavailable"}
