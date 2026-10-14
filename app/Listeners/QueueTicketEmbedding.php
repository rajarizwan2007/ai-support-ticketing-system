<?php

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Jobs\EmbedModel;

/**
 * Queue the embedding used for similar-ticket search. Runs during the request;
 * the slow part is the queued job, which has its own retries.
 */
class QueueTicketEmbedding
{
    public function handle(TicketCreated $event): void
    {
        EmbedModel::dispatch($event->ticket);
    }
}
