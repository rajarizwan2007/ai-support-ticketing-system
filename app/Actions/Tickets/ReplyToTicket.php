<?php

namespace App\Actions\Tickets;

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Events\TicketReplied;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class ReplyToTicket
{
    public function __construct(private UpdateTicket $updateTicket, private AddAttachments $addAttachments) {}

    /**
     * Add a reply or internal note with any attached files, recording the first
     * staff response time. A customer reply reopens a ticket that is pending,
     * resolved or closed. A listener emails the other side of a public reply.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(Ticket $ticket, User $user, string $body, bool $isInternal = false, array $files = []): void
    {
        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'type' => $isInternal ? MessageType::InternalNote : MessageType::Reply,
            'body' => $body,
        ]);

        $this->addAttachments->handle($ticket, $user, $files, $message);

        TicketReplied::dispatch($ticket, $message);

        $isCustomer = $user->hasRole(Role::CUSTOMER);

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
