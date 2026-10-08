<?php

namespace Database\Factories;

use App\Enums\MessageType;
use App\Models\Message;
use App\Models\Scopes\OrganizationScope;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'organization_id' => fn (array $attributes) => $this->ticket($attributes)->organization_id,
            'user_id' => fn (array $attributes) => $this->ticket($attributes)->requester_id,
            'type' => MessageType::Reply,
            'body' => fake()->paragraph(),
            'is_ai_generated' => false,
        ];
    }

    /**
     * The message's ticket, read past the tenant scope so the message always
     * copies its organization from the ticket.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function ticket(array $attributes): Ticket
    {
        return Ticket::withoutGlobalScope(OrganizationScope::class)->findOrFail($attributes['ticket_id']);
    }

    /**
     * An agent-only note, hidden from the customer.
     */
    public function internalNote(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MessageType::InternalNote,
        ]);
    }

    /**
     * An automatic message with no author, e.g. "Status changed to Resolved".
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MessageType::System,
            'user_id' => null,
        ]);
    }
}
