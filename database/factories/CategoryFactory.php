<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'organization_id' => Organization::factory(),
            'parent_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
        ];
    }

    /**
     * Nest the category under a parent in the same organization.
     */
    public function childOf(Category $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $parent->organization_id,
            'parent_id' => $parent->id,
        ]);
    }
}
