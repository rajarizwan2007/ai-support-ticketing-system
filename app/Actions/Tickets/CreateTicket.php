<?php

namespace App\Actions\Tickets;

use App\Enums\Priority;
use App\Events\TicketCreated;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class CreateTicket
{
    public function __construct(private AddAttachments $addAttachments) {}

    /**
     * Open a ticket for the requester, with the next reference and SLA due
     * dates from the organization's active policy for its priority, and store
     * any attached files. Listeners email the staff and queue the embedding.
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

        TicketCreated::dispatch($ticket);

        return $ticket;
    }
}
