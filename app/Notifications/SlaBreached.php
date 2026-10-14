<?php

namespace App\Notifications;

use App\Enums\SlaTarget;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells admins and the assignee that a ticket missed an SLA deadline.
 */
class SlaBreached extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket, public SlaTarget $target) {}

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
            ->error()
            ->subject("[{$this->ticket->reference}] {$this->target->label()} SLA breached")
            ->line("{$this->ticket->reference} missed its {$this->target->label()} deadline.")
            ->line($this->ticket->subject)
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
            'text' => "{$this->ticket->reference} missed its {$this->target->label()} SLA deadline.",
        ];
    }
}
