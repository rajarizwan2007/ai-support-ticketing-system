<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the other side (the customer, or the assigned agent) about a new public reply.
 */
class TicketReplied extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket, public Message $message) {}

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
            ->subject("[{$this->ticket->reference}] New reply: {$this->ticket->subject}")
            ->line("{$this->message->author?->name} replied:")
            ->line($this->message->body)
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
            'text' => "{$this->message->author?->name} replied on {$this->ticket->reference}: {$this->ticket->subject}",
        ];
    }
}
