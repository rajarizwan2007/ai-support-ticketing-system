<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\Sentiment;
use App\Enums\SlaTarget;
use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property TicketStatus $status
 * @property Priority $priority
 */
#[Fillable([
    'reference',
    'requester_id',
    'assignee_id',
    'category_id',
    'sla_policy_id',
    'subject',
    'description',
    'status',
    'priority',
    'channel',
    'first_response_due_at',
    'resolution_due_at',
    'first_responded_at',
    'resolved_at',
    'closed_at',
    'ai_summary',
    'ai_sentiment',
])]
#[Hidden(['embedding'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * Recalculate the SLA due dates when the priority changes. Stamp resolved_at
     * and closed_at when the status changes, and clear them when the ticket is reopened.
     */
    protected static function booted(): void
    {
        static::updating(function (Ticket $ticket): void {
            if ($ticket->isDirty('priority')) {
                $ticket->applySlaPolicy();
            }

            if (! $ticket->isDirty('status')) {
                return;
            }

            if ($ticket->status === TicketStatus::Resolved) {
                $ticket->resolved_at = now();
                $ticket->closed_at = null;
            } elseif ($ticket->status === TicketStatus::Closed) {
                $ticket->resolved_at ??= now();
                $ticket->closed_at = now();
            } else {
                $ticket->resolved_at = null;
                $ticket->closed_at = null;
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => Priority::class,
            'channel' => TicketChannel::class,
            'ai_sentiment' => Sentiment::class,
            'embedding' => AsVector::class,
            'first_response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'first_response_breached_at' => 'datetime',
            'resolution_breached_at' => 'datetime',
        ];
    }

    /**
     * Use the organization's active SLA policy for the ticket's priority and set
     * the due dates from when the ticket was created. No policy clears them.
     * New deadlines reset the breach markers, so a breach of a new deadline
     * is escalated again.
     */
    public function applySlaPolicy(): void
    {
        $sla = SlaPolicy::where('priority', $this->priority)->where('is_active', true)->first();
        $start = $this->created_at ?? now();

        $this->sla_policy_id = $sla?->id;
        $this->first_response_due_at = $sla ? $start->copy()->addMinutes($sla->first_response_minutes) : null;
        $this->resolution_due_at = $sla ? $start->copy()->addMinutes($sla->resolution_minutes) : null;
        $this->first_response_breached_at = null;
        $this->resolution_breached_at = null;
    }

    /**
     * Absolute link to the ticket on its organization's subdomain.
     */
    public function url(): string
    {
        return $this->organization->url(route('tickets.show', $this->reference, false));
    }

    /**
     * The text that is embedded for similarity search.
     */
    public function embeddingText(): string
    {
        return $this->subject."\n\n".$this->description;
    }

    /**
     * Open tickets past the target's deadline that have not met it and have
     * not been escalated for it yet.
     *
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function slaBreached(Builder $query, SlaTarget $target): void
    {
        $query->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed])
            ->whereNull($target->metColumn())
            ->whereNull($target->breachedColumn())
            ->where($target->dueColumn(), '<', now());
    }

    /**
     * Customers only see the tickets they requested; agents and admins see all.
     *
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->hasRole(Role::CUSTOMER)) {
            $query->where('requester_id', $user->id);
        }
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<SlaPolicy, $this>
     */
    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Every file on the ticket, including those uploaded with messages.
     *
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }
}
