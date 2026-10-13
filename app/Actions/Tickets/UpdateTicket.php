<?php

namespace App\Actions\Tickets;

use App\Enums\MessageType;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;

class UpdateTicket
{
    /**
     * Update the ticket, log each real change as a system message, and email
     * a new assignee (unless they assigned it to themselves).
     *
     * @param  array{status?: string, priority?: string, assignee_id?: int|string|null}  $attributes
     */
    public function handle(Ticket $ticket, User $user, array $attributes): void
    {
        $ticket->update($attributes);

        $changes = [];

        if ($ticket->wasChanged('status')) {
            $changes[] = 'Status changed to '.str_replace('_', ' ', $ticket->status->value);
        }

        if ($ticket->wasChanged('priority')) {
            $changes[] = 'Priority changed to '.$ticket->priority->value;
        }

        if ($ticket->wasChanged('assignee_id')) {
            $changes[] = $ticket->assignee ? 'Assigned to '.$ticket->assignee->name : 'Unassigned';

            if ($ticket->assignee?->isNot($user)) {
                $ticket->assignee->notify(new TicketAssigned($ticket, $user));
            }
        }

        foreach ($changes as $change) {
            $ticket->messages()->create([
                'type' => MessageType::System,
                'body' => $change.' by '.$user->name.'.',
            ]);
        }
    }
}
