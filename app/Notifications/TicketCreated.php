<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells staff that a customer opened a ticket.
 */
class TicketCreated extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

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
            ->subject("[{$this->ticket->reference}] New ticket: {$this->ticket->subject}")
            ->line("{$this->ticket->requester->name} opened a {$this->ticket->priority->value} priority ticket.")
            ->line($this->ticket->subject)
            ->action('View ticket', $this->ticket->url());
    }
}
