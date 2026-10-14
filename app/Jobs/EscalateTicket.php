<?php

namespace App\Jobs;

use App\Enums\MessageType;
use App\Enums\SlaTarget;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\SlaBreached;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Record an SLA breach on the ticket and alert the organization's admins
 * and the assignee.
 */
class EscalateTicket implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket, public SlaTarget $target) {}

    public function handle(): void
    {
        $due = $this->ticket->{$this->target->dueColumn()};

        $this->ticket->messages()->create([
            'type' => MessageType::System,
            'body' => "{$this->target->label()} SLA breached".($due ? ": it was due {$due->toDayDateTimeString()} (UTC)." : '.'),
        ]);

        $recipients = User::whereHas('role', fn (Builder $role) => $role->where('slug', Role::ADMIN))
            ->when($this->ticket->assignee_id, fn (Builder $query, int $assigneeId) => $query->orWhere('id', $assigneeId))
            ->get();

        Notification::send($recipients, new SlaBreached($this->ticket, $this->target));
    }
}
