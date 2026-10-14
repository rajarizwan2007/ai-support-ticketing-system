<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A ticket's status changed. No listeners yet; the SLA and in-app notification work will use it.
 */
class TicketStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(public Ticket $ticket, public User $changedBy) {}
}
