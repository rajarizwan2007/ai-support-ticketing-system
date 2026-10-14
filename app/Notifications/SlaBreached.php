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
        return ['mail'];
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
}
