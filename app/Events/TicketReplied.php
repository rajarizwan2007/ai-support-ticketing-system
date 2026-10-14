<?php

namespace App\Events;

use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A reply or internal note was added to a ticket.
 */
class TicketReplied
{
    use Dispatchable, SerializesModels;

    public function __construct(public Ticket $ticket, public Message $message) {}
}
