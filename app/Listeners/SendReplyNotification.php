<?php

namespace App\Listeners;

use App\Enums\MessageType;
use App\Events\TicketReplied;
use App\Models\Role;
use App\Notifications\TicketReplied as TicketRepliedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Email the other side of a public reply: a staff reply goes to the customer,
 * a customer reply to the assigned agent. Internal notes email nobody.
 */
class SendReplyNotification implements ShouldQueue
{
    public function handle(TicketReplied $event): void
    {
        $ticket = $event->ticket;
        $author = $event->message->author;

        if ($event->message->type !== MessageType::Reply || ! $author) {
            return;
        }

        $recipient = $author->hasRole(Role::CUSTOMER) ? $ticket->assignee : $ticket->requester;

        if ($recipient && $recipient->isNot($author)) {
            $recipient->notify(new TicketRepliedNotification($ticket, $event->message));
        }
    }
}
