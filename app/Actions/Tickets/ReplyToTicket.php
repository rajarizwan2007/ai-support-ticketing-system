<?php

namespace App\Actions\Tickets;

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketReplied;

class ReplyToTicket
{
    public function __construct(private UpdateTicket $updateTicket) {}

    /**
     * Add a reply or internal note, recording the first staff response time.
     * A customer reply reopens a ticket that is pending, resolved or closed.
     * A public reply emails the other side: the customer, or the assigned agent.
     */
    public function handle(Ticket $ticket, User $user, string $body, bool $isInternal = false): void
    {
        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'type' => $isInternal ? MessageType::InternalNote : MessageType::Reply,
            'body' => $body,
        ]);

        $isCustomer = $user->hasRole(Role::CUSTOMER);
        $recipient = $isCustomer ? $ticket->assignee : $ticket->requester;

        if (! $isInternal && $recipient && $recipient->isNot($user)) {
            $recipient->notify(new TicketReplied($ticket, $message));
        }

        if (! $isCustomer) {
            if (! $isInternal && ! $ticket->first_responded_at) {
                $ticket->update(['first_responded_at' => now()]);
            }

            return;
        }

        if (in_array($ticket->status, [TicketStatus::Pending, TicketStatus::Resolved, TicketStatus::Closed], true)) {
            $this->updateTicket->handle($ticket, $user, ['status' => TicketStatus::Open->value]);
        }
    }
}
