<?php

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Models\User;
use App\Notifications\TicketCreated as TicketCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Email the organization's staff about a new ticket (except the requester).
 */
class SendNewTicketNotification implements ShouldQueue
{
    public function handle(TicketCreated $event): void
    {
        Notification::send(
            User::staff()->whereKeyNot($event->ticket->requester_id)->get(),
            new TicketCreatedNotification($event->ticket),
        );
    }
}
