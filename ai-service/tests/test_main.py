import json

import httpx
import pytest
from fastapi.testclient import TestClient

from app.main import app, get_ollama
from app.ollama import Ollama


def fake_ollama(handler) -> Ollama:
    """An Ollama client whose HTTP calls are answered by `handler`, never the network."""
    client = httpx.AsyncClient(base_url="http://ollama", transport=httpx.MockTransport(handler))
    return Ollama(client, "test-model")


@pytest.fixture
def client():
    with TestClient(app) as test_client:
        yield test_client
    app.dependency_overrides.clear()


def use(handler) -> None:
    app.dependency_overrides[get_ollama] = lambda: fake_ollama(handler)


@pytest.mark.parametrize(
    ("handler", "ollama_up"),
    [
        (lambda request: httpx.Response(200, json={"version": "0.35.0"}), True),
        (lambda request: httpx.Response(500), False),
    ],
)
def test_health_reports_whether_ollama_answers(client, handler, ollama_up):
    use(handler)

    response = client.get("/health")

    assert response.status_code == 200
    assert response.json() == {"status": "ok", "ollama": ollama_up}


def test_health_is_ok_when_ollama_is_unreachable(client):
    def unreachable(request):
        raise httpx.ConnectError("refused")

    use(unreachable)

    assert client.get("/health").json() == {"status": "ok", "ollama": False}


def test_embed_returns_one_vector_per_text(client):
    sent = {}

    def handler(request):
        sent["json"] = json.loads(request.content)
        return httpx.Response(200, json={"embeddings": [[0.1, 0.2], [0.3, 0.4]]})

    use(handler)

    response = client.post("/embed", json={"texts": ["printer broken", "login fails"]})

    assert response.status_code == 200
    assert response.json() == {"model": "test-model", "embeddings": [[0.1, 0.2], [0.3, 0.4]]}
    assert sent["json"] == {"model": "test-model", "input": ["printer broken", "login fails"]}


@pytest.mark.parametrize("body", [{"texts": []}, {"texts": [""]}, {}, {"texts": ["x"] * 33}])
def test_embed_rejects_invalid_input(client, body):
    use(lambda request: httpx.Response(200, json={"embeddings": []}))

    assert client.post("/embed", json=body).status_code == 422


def test_embed_returns_503_when_ollama_fails(client):
    use(lambda request: httpx.Response(500))

    response = client.post("/embed", json={"texts": ["hello"]})

    assert response.status_code == 503
    assert response.json() == {"detail": "Embedding model unavailable"}
