<?php

namespace App\Actions\Tickets;

use App\Enums\MessageType;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;

class ReplyToTicket
{
    /**
     * Add a reply or internal note, recording the first staff response time.
     */
    public function handle(Ticket $ticket, User $user, string $body, bool $isInternal = false): void
    {
        $ticket->messages()->create([
            'user_id' => $user->id,
            'type' => $isInternal ? MessageType::InternalNote : MessageType::Reply,
            'body' => $body,
        ]);

        if (! $isInternal && ! $ticket->first_responded_at && ! $user->hasRole(Role::CUSTOMER)) {
            $ticket->update(['first_responded_at' => now()]);
        }
    }
}
