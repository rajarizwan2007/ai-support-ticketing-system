<?php

namespace App\Jobs;

use App\Models\KbArticle;
use App\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Store an embedding of a ticket or KB article, used for similar tickets and
 * to find KB articles for suggested replies. The Python AI service (`ai`)
 * calls the local model in the `ollama` container and says which model it used.
 */
class EmbedModel implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public Ticket|KbArticle $model) {}

    /**
     * Saved without events or a new updated_at: storing a vector isn't an edit.
     * The 130 s timeout outlasts the service's own 120 s Ollama call, so a slow
     * model comes back as the service's 503 rather than a dropped connection.
     */
    public function handle(): void
    {
        $response = Http::timeout(130)
            ->post(config('services.ai.url').'/embed', ['texts' => [$this->model->embeddingText()]])
            ->throw();

        $this->model->withoutTimestamps(fn () => $this->model->forceFill([
            'embedding' => $response->json('embeddings.0'),
            'embedding_model' => $response->json('model'),
        ])->saveQuietly());
    }
}
