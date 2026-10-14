<?php

namespace App\Actions\Tickets;

use App\Enums\KbArticleStatus;
use App\Models\KbArticle;
use App\Models\Ticket;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class SuggestReply
{
    /**
     * How close (cosine similarity) a KB article must be to the ticket to be used.
     */
    public const MIN_SIMILARITY = 0.5;

    /**
     * Draft a reply with the Python AI service, grounded in the organization's
     * published KB articles closest to the ticket (RAG). An agent edits the
     * draft before sending. The 55 s timeout stays under nginx's 60 s.
     *
     * @return array{body: string, sources: array<int, array{id: int, title: string}>}
     *
     * @throws ConnectionException|RequestException when the AI service is down or fails
     */
    public function handle(Ticket $ticket): array
    {
        $articles = $ticket->embedding
            ? KbArticle::where('status', KbArticleStatus::Published)
                ->whereVectorSimilarTo('embedding', $ticket->embedding, minSimilarity: self::MIN_SIMILARITY)
                ->limit(3)
                ->get(['id', 'title', 'body'])
            : collect();

        $response = Http::timeout(55)
            ->post(config('services.ai.url').'/suggest-reply', [
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'articles' => $articles->map->only('id', 'title', 'body')->values()->all(),
            ])
            ->throw();

        return [
            'body' => (string) $response->json('reply'),
            'sources' => $articles
                ->whereIn('id', $response->json('source_ids'))
                ->map(fn (KbArticle $article) => ['id' => $article->id, 'title' => $article->title])
                ->values()
                ->all(),
        ];
    }
}
