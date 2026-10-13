<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an agent that someone assigned a ticket to them.
 */
class TicketAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket, public User $assignedBy) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->ticket->reference}] Assigned to you: {$this->ticket->subject}")
            ->line("{$this->assignedBy->name} assigned this ticket to you.")
            ->action('View ticket', $this->ticket->url());
    }
}
