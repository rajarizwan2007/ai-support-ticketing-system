<?php

namespace App\Listeners;

use App\Events\TicketAssigned;
use App\Notifications\TicketAssigned as TicketAssignedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Email the new assignee, unless they assigned the ticket to themselves.
 */
class SendAssignmentNotification implements ShouldQueue
{
    public function handle(TicketAssigned $event): void
    {
        $assignee = $event->ticket->assignee;

        if ($assignee?->isNot($event->assignedBy)) {
            $assignee->notify(new TicketAssignedNotification($event->ticket, $event->assignedBy));
        }
    }
}
