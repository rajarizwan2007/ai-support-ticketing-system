<?php

namespace App\Actions\Tickets;

use App\Enums\Priority;
use App\Jobs\EmbedTicket;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCreated;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

class CreateTicket
{
    public function __construct(private AddAttachments $addAttachments) {}

    /**
     * Open a ticket for the requester, with the next reference and SLA due
     * dates from the organization's active policy for its priority, store
     * any attached files, and email the organization's staff (except the requester).
     * The embedding for similar-ticket search is made in the background.
     *
     * @param  array{subject: string, description: string, category_id?: int|string|null, priority?: string}  $attributes
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(User $requester, array $attributes, array $files = []): Ticket
    {
        $ticket = new Ticket([
            ...$attributes,
            'reference' => 'TKT-'.(1001 + Ticket::withTrashed()->count()),
            'requester_id' => $requester->id,
            'priority' => $attributes['priority'] ?? Priority::Medium,
        ]);

        $ticket->applySlaPolicy();
        $ticket->save();

        $this->addAttachments->handle($ticket, $requester, $files);

        EmbedTicket::dispatch($ticket);
        Notification::send(User::staff()->whereKeyNot($requester->id)->get(), new TicketCreated($ticket));

        return $ticket;
    }
}
