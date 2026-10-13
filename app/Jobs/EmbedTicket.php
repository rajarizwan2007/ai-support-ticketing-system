<?php

namespace App\Jobs;

use App\Models\Ticket;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Store an embedding of the ticket's subject and description, used to find
 * similar tickets. The model runs locally in the `ollama` container.
 */
class EmbedTicket implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public Ticket $ticket) {}

    public function handle(): void
    {
        $model = config('services.ollama.embedding_model');

        $embedding = Http::timeout(120)
            ->post(config('services.ollama.url').'/api/embed', [
                'model' => $model,
                'input' => $this->ticket->subject."\n\n".$this->ticket->description,
            ])
            ->throw()
            ->json('embeddings.0');

        $this->ticket->forceFill(['embedding' => $embedding, 'embedding_model' => $model])->saveQuietly();
    }
}
