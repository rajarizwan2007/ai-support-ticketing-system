<?php

namespace App\Actions\Tickets;

use App\Enums\Priority;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCreated;
use Illuminate\Support\Facades\Notification;

class CreateTicket
{
    /**
     * Open a ticket for the requester, with the next reference and SLA due
     * dates from the organization's active policy for its priority, and
     * email the organization's staff (except the requester).
     *
     * @param  array{subject: string, description: string, category_id?: int|string|null, priority?: string}  $attributes
     */
    public function handle(User $requester, array $attributes): Ticket
    {
        $ticket = new Ticket([
            ...$attributes,
            'reference' => 'TKT-'.(1001 + Ticket::withTrashed()->count()),
            'requester_id' => $requester->id,
            'priority' => $attributes['priority'] ?? Priority::Medium,
        ]);

        $ticket->applySlaPolicy();
        $ticket->save();

        Notification::send(User::staff()->whereKeyNot($requester->id)->get(), new TicketCreated($ticket));

        return $ticket;
    }
}
