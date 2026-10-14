<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an agent that someone assigned a ticket to them.
 */
class TicketAssigned extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket, public User $assignedBy) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->ticket->reference}] Assigned to you: {$this->ticket->subject}")
            ->line("{$this->assignedBy->name} assigned this ticket to you.")
            ->action('View ticket', $this->ticket->url());
    }

    /**
     * The in-app notification shown under the bell.
     *
     * @return array{ticket_reference: string, text: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_reference' => $this->ticket->reference,
            'text' => "{$this->assignedBy->name} assigned {$this->ticket->reference} to you: {$this->ticket->subject}",
        ];
    }
}
