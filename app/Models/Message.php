<?php

namespace App\Models;

use App\Enums\MessageType;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Scopes\OrganizationScope;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'type', 'body', 'is_ai_generated'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * A message always belongs to its ticket's organization.
     */
    protected static function booted(): void
    {
        static::creating(function (Message $message): void {
            $message->organization_id = Ticket::withoutGlobalScope(OrganizationScope::class)
                ->whereKey($message->ticket_id)
                ->value('organization_id');
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
            'type' => MessageType::class,
            'is_ai_generated' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * The user who wrote the message; null for system messages.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
