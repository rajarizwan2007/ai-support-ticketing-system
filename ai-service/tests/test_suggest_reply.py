import json

import httpx
import pytest
from fastapi.testclient import TestClient

from app.main import app, get_ollama
from app.ollama import Ollama
from app.prompts import clean_reply

TICKET = {"subject": "Charged twice", "description": "Please refund one payment."}
ARTICLES = [
    {"id": 7, "title": "Duplicate charges", "body": "Refunded within 5 business days."},
    {"id": 9, "title": "Invoices", "body": "Download them from the billing page."},
]


@pytest.fixture
def sent():
    return {}


@pytest.fixture
def answer_with(sent):
    """Make the chat model answer with `content`, recording what it was sent."""

    def use(content: str, status: int = 200) -> None:
        def handler(request):
            sent["json"] = json.loads(request.content)
            return httpx.Response(status, json={"message": {"content": content}})

        client = httpx.AsyncClient(base_url="http://ollama", transport=httpx.MockTransport(handler))
        app.dependency_overrides[get_ollama] = lambda: Ollama(client, "embed-model", "chat-model")

    yield use
    app.dependency_overrides.clear()


@pytest.fixture
def client():
    with TestClient(app) as test_client:
        yield test_client


def prompt(sent) -> str:
    return sent["json"]["messages"][1]["content"]


def test_returns_the_draft_with_the_given_articles_as_sources(client, answer_with, sent):
    answer_with("We refund duplicate charges within 5 business days.")

    response = client.post("/suggest-reply", json={**TICKET, "articles": ARTICLES})

    assert response.status_code == 200
    assert response.json() == {
        "reply": "We refund duplicate charges within 5 business days.",
        "source_ids": [7, 9],
        "model": "chat-model",
        "prompt": "suggest_reply_v1",
    }
    assert '<article id="7">\nDuplicate charges' in prompt(sent)
    assert "<ticket>\nSubject: Charged twice" in prompt(sent)
    assert prompt(sent).endswith("in your own words.")


def test_without_articles_it_asks_for_a_checking_reply(client, answer_with, sent):
    answer_with("We are checking with the team.")

    response = client.post("/suggest-reply", json=TICKET)

    assert response.json()["source_ids"] == []
    assert "<article" not in prompt(sent)
    assert prompt(sent).endswith("ask for any details you need.")


def test_long_articles_are_cut(client, answer_with, sent):
    answer_with("Hi")
    article = {"id": 1, "title": "Long", "body": "a" * 5000 + "END"}

    client.post("/suggest-reply", json={**TICKET, "articles": [article]})

    assert "END" not in prompt(sent)


@pytest.mark.parametrize(
    ("raw", "clean"),
    [
        (
            "Okay, here's a reply to the customer:\n\n“Check your spam folder.”",
            "Check your spam folder.",
        ),
        ("We are checking.\n\n<article>\nMore text.</article>", "We are checking.\n\nMore text."),
        ('"Thanks for waiting."', "Thanks for waiting."),
        ("Plain reply.", "Plain reply."),
    ],
)
def test_clean_reply_strips_wrapping(raw, clean):
    assert clean_reply(raw) == clean


@pytest.mark.parametrize(
    "body",
    [{}, {**TICKET, "subject": ""}, {**TICKET, "articles": ARTICLES * 3}],
)
def test_rejects_invalid_input(client, answer_with, body):
    answer_with("Hi")

    assert client.post("/suggest-reply", json=body).status_code == 422


def test_502_when_the_model_returns_nothing(client, answer_with):
    answer_with("  “”  ")

    assert client.post("/suggest-reply", json=TICKET).status_code == 502


def test_503_when_the_model_is_down(client, answer_with):
    answer_with("", status=500)

    assert client.post("/suggest-reply", json=TICKET).status_code == 503
