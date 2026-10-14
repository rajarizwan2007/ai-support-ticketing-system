<?php

namespace Database\Seeders;

use App\Enums\Priority;
use App\Jobs\EmbedModel;
use App\Models\Category;
use App\Models\KbArticle;
use App\Models\Message;
use App\Models\Organization;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Sample tickets in pairs on the same topic, so similar-ticket search has matches.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const SAMPLE_TICKETS = [
        ['Cannot log in to my account', 'I enter my email and password but the login page just reloads. I tried two browsers.'],
        ['Login keeps failing after password change', 'Since I changed my password yesterday I get "invalid credentials" every time I sign in.'],
        ['Password reset email never arrives', 'I requested a password reset three times but no email came, not even in spam.'],
        ['No reset link in my inbox', 'The forgot password form says an email was sent, but nothing arrives.'],
        ['Charged twice this month', 'My card shows two charges for the same monthly subscription. Please refund one.'],
        ['Duplicate payment on my invoice', 'I was billed twice for October. Can you check and reverse the extra payment?'],
        ['Request a refund for annual plan', 'We switched to monthly by mistake after paying for a year. I would like a refund.'],
        ['How do I get my money back?', 'I cancelled within the trial period but was still charged. How can I get a refund?'],
        ['Export to CSV is empty', 'When I export my reports to CSV the file downloads but has only the header row.'],
        ['CSV download contains no data', 'The exported spreadsheet is blank apart from the column names.'],
        ['Dashboard is very slow', 'The dashboard takes over 30 seconds to load since this morning.'],
        ['Pages take forever to load', 'Every page in the app is extremely slow today, sometimes it times out.'],
        ['Add a new user to our team', 'How can I invite a colleague to our organization account?'],
        ['Invite a teammate', 'I want to give my coworker access. Where do I send an invitation?'],
        ['Change the email on my account', 'I have a new work email address. How do I update it on my profile?'],
        ['Update my contact email', 'Please change my account email to my new address.'],
        ['Mobile app crashes on startup', 'The iPhone app closes immediately after the splash screen.'],
        ['App closes as soon as I open it', 'On Android the app crashes right away since the last update.'],
        ['Notifications not received', 'I stopped getting email notifications when someone replies to my tickets.'],
        ['Not getting reply emails', 'Agents reply to my tickets but I never receive the email alerts.'],
    ];

    /**
     * Seed the roles and two demo organizations, so tenant isolation can be
     * checked by hand (acme.localhost and globex.localhost).
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $this->seedOrganization('Acme Inc', 'acme', 'example.com');
        $this->seedOrganization('Globex Corp', 'globex', 'globex.example.com');

        Organization::forgetCurrent();
    }

    /**
     * Seed one organization with an admin, agents, customers, SLA policies,
     * categories, sample tickets and KB articles. The first agent and customer
     * get predictable logins (agent@ / customer@ the given email domain).
     */
    private function seedOrganization(string $name, string $slug, string $emailDomain): void
    {
        $organization = Organization::factory()->create([
            'name' => $name,
            'slug' => $slug,
        ])->makeCurrent();

        User::factory()->admin()->for($organization)->create([
            'name' => 'Admin User',
            'email' => "admin@{$emailDomain}",
        ]);

        $agents = User::factory()->agent()->for($organization)->count(3)->sequence(
            ['name' => 'Agent User', 'email' => "agent@{$emailDomain}"],
            [],
            [],
        )->create();

        $customers = User::factory()->customer()->for($organization)->count(5)->sequence(
            ['name' => 'Customer User', 'email' => "customer@{$emailDomain}"],
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

        foreach (self::SAMPLE_TICKETS as $index => [$subject, $description]) {
            $number = $index + 1;
            $priority = fake()->randomElement(Priority::cases());
            $sla = $slaPolicies[$priority->value];
            $createdAt = now()->subHours(fake()->numberBetween(1, 72));

            $ticket = Ticket::factory()->for($organization)->create([
                'reference' => 'TKT-'.(1000 + $number),
                'subject' => $subject,
                'description' => $description,
                'requester_id' => $customers->random()->id,
                'assignee_id' => fake()->boolean(70) ? $agents->random()->id : null,
                'category_id' => $categories->random()->id,
                'sla_policy_id' => $sla->id,
                'priority' => $priority,
                'first_response_due_at' => $createdAt->copy()->addMinutes($sla->first_response_minutes),
                'resolution_due_at' => $createdAt->copy()->addMinutes($sla->resolution_minutes),
                'created_at' => $createdAt,
            ]);

            EmbedModel::dispatch($ticket);

            Message::factory()->for($ticket)->create(['user_id' => $ticket->requester_id]);

            if ($ticket->assignee_id) {
                Message::factory()->for($ticket)->create(['user_id' => $ticket->assignee_id]);
                Message::factory()->internalNote()->for($ticket)->create(['user_id' => $ticket->assignee_id]);
            }
        }

        KbArticle::factory()->published()->for($organization)->count(6)->create([
            'author_id' => fn () => $agents->random()->id,
            'category_id' => fn () => $categories->random()->id,
        ])->each(fn (KbArticle $article) => EmbedModel::dispatch($article));
    }
}
