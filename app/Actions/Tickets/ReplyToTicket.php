<?php

namespace App\Actions\Tickets;

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Events\TicketReplied;
use App\Models\Message;
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
     * $isAiGenerated marks a message an agent started from an AI draft.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(Ticket $ticket, User $user, string $body, bool $isInternal = false, array $files = [], bool $isAiGenerated = false): Message
    {
        $message = $ticket->messages()->create([
            'user_id' => $user->id,
            'type' => $isInternal ? MessageType::InternalNote : MessageType::Reply,
            'body' => $body,
            'is_ai_generated' => $isAiGenerated,
        ]);

        $this->addAttachments->handle($ticket, $user, $files, $message);

        TicketReplied::dispatch($ticket, $message);

        $isCustomer = $user->hasRole(Role::CUSTOMER);

        if (! $isCustomer) {
            if (! $isInternal && ! $ticket->first_responded_at) {
                $ticket->update(['first_responded_at' => now()]);
            }

            return $message;
        }

        if (in_array($ticket->status, [TicketStatus::Pending, TicketStatus::Resolved, TicketStatus::Closed], true)) {
            $this->updateTicket->handle($ticket, $user, ['status' => TicketStatus::Open->value]);
        }

        return $message;
    }
}
