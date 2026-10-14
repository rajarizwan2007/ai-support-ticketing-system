<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A ticket was opened.
 */
class TicketCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Ticket $ticket) {}
}
