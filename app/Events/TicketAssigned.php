<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A ticket was assigned to a staff member.
 */
class TicketAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(public Ticket $ticket, public User $assignedBy) {}
}
