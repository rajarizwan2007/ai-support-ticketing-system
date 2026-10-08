<?php

namespace Database\Seeders;

use App\Enums\Priority;
use App\Models\Category;
use App\Models\KbArticle;
use App\Models\Message;
use App\Models\Organization;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $organization = Organization::factory()->create([
            'name' => 'Acme Inc',
            'slug' => 'acme',
        ])->makeCurrent();

        User::factory()->admin()->for($organization)->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $agents = User::factory()->agent()->for($organization)->count(3)->sequence(
            ['name' => 'Agent User', 'email' => 'agent@example.com'],
            [],
            [],
        )->create();

        $customers = User::factory()->customer()->for($organization)->count(5)->sequence(
            ['name' => 'Customer User', 'email' => 'customer@example.com'],
            [],
            [],
            [],
            [],
        )->create();

        $slaPolicies = collect(Priority::cases())->mapWithKeys(fn (Priority $priority) => [
            $priority->value => SlaPolicy::factory()->for($organization)->priority($priority)->create(),
        ]);

        $billing = Category::factory()->for($organization)->create(['name' => 'Billing', 'slug' => 'billing']);
        $categories = collect([
            $billing,
            Category::factory()->childOf($billing)->create(['name' => 'Refunds', 'slug' => 'refunds']),
            Category::factory()->for($organization)->create(['name' => 'Technical', 'slug' => 'technical']),
            Category::factory()->for($organization)->create(['name' => 'Account', 'slug' => 'account']),
        ]);

        foreach (range(1, 20) as $number) {
            $priority = fake()->randomElement(Priority::cases());
            $sla = $slaPolicies[$priority->value];
            $createdAt = now()->subHours(fake()->numberBetween(1, 72));

            $ticket = Ticket::factory()->for($organization)->create([
                'reference' => 'TKT-'.(1000 + $number),
                'requester_id' => $customers->random()->id,
                'assignee_id' => fake()->boolean(70) ? $agents->random()->id : null,
                'category_id' => $categories->random()->id,
                'sla_policy_id' => $sla->id,
                'priority' => $priority,
                'first_response_due_at' => $createdAt->copy()->addMinutes($sla->first_response_minutes),
                'resolution_due_at' => $createdAt->copy()->addMinutes($sla->resolution_minutes),
                'created_at' => $createdAt,
            ]);

            Message::factory()->for($ticket)->create(['user_id' => $ticket->requester_id]);

            if ($ticket->assignee_id) {
                Message::factory()->for($ticket)->create(['user_id' => $ticket->assignee_id]);
                Message::factory()->internalNote()->for($ticket)->create(['user_id' => $ticket->assignee_id]);
            }
        }

        KbArticle::factory()->published()->for($organization)->count(6)->create([
            'author_id' => fn () => $agents->random()->id,
            'category_id' => fn () => $categories->random()->id,
        ]);
    }
}
