<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Scopes\OrganizationScope;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
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
            'message_id' => null,
            'user_id' => fn (array $attributes) => Ticket::withoutGlobalScope(OrganizationScope::class)
                ->whereKey($attributes['ticket_id'])
                ->value('requester_id'),
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1_000, 500_000),
        ];
    }
}
