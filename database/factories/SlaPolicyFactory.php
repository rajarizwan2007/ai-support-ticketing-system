<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Models\Organization;
use App\Models\SlaPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlaPolicy>
 */
class SlaPolicyFactory extends Factory
{
    /**
     * Response and resolution targets in minutes, per priority.
     *
     * @var array<string, array{int, int}>
     */
    private const TARGETS = [
        'low' => [24 * 60, 5 * 24 * 60],
        'medium' => [8 * 60, 3 * 24 * 60],
        'high' => [2 * 60, 24 * 60],
        'urgent' => [30, 4 * 60],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => fn () => Organization::currentId() ?? Organization::factory(),
            ...$this->forPriority(fake()->randomElement(Priority::cases())),
            'business_hours_only' => false,
            'is_active' => true,
        ];
    }

    /**
     * Use the standard targets for the given priority.
     */
    public function priority(Priority $priority): static
    {
        return $this->state(fn (array $attributes) => $this->forPriority($priority));
    }

    /**
     * @return array<string, mixed>
     */
    private function forPriority(Priority $priority): array
    {
        [$response, $resolution] = self::TARGETS[$priority->value];

        return [
            'name' => ucfirst($priority->value).' priority',
            'priority' => $priority,
            'first_response_minutes' => $response,
            'resolution_minutes' => $resolution,
        ];
    }
}
