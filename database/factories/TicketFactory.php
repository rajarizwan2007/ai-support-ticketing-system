<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => fn () => Organization::currentId() ?? Organization::factory(),
            'reference' => 'TKT-'.fake()->unique()->numberBetween(1000, 999999),
            'requester_id' => fn (array $attributes) => User::factory()->customer()->state([
                'organization_id' => $attributes['organization_id'],
            ]),
            'assignee_id' => null,
            'category_id' => null,
            'sla_policy_id' => null,
            'subject' => rtrim(fake()->sentence(6), '.'),
            'description' => fake()->paragraphs(2, true),
            'status' => TicketStatus::Open,
            'priority' => fake()->randomElement(Priority::cases()),
            'channel' => fake()->randomElement(TicketChannel::cases()),
        ];
    }

    /**
     * Assign the ticket to a new agent in the same organization.
     */
    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignee_id' => fn (array $attributes) => User::factory()->agent()->state([
                'organization_id' => $attributes['organization_id'],
            ]),
        ]);
    }

    /**
     * Mark the ticket as resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Resolved,
            'first_responded_at' => now()->subDays(2),
            'resolved_at' => now()->subDay(),
        ]);
    }
}
