<?php

namespace Database\Factories;

use App\Enums\KbArticleStatus;
use App\Models\KbArticle;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KbArticle>
 */
class KbArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(5), '.');

        return [
            'organization_id' => Organization::factory(),
            'category_id' => null,
            'author_id' => fn (array $attributes) => User::factory()->agent()->state([
                'organization_id' => $attributes['organization_id'],
            ]),
            'title' => $title,
            'slug' => Str::slug($title),
            'body' => fake()->paragraphs(4, true),
            'status' => KbArticleStatus::Draft,
            'published_at' => null,
        ];
    }

    /**
     * Mark the article as published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KbArticleStatus::Published,
            'published_at' => now()->subWeek(),
        ]);
    }
}
