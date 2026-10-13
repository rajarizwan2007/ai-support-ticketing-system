<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketCreated;
use App\Notifications\TicketReplied;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inOrganization();
        Notification::fake();
    }

    public function test_a_new_ticket_emails_the_organizations_staff(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();
        $otherCustomer = User::factory()->customer()->create();

        $this->actingAs(User::factory()->customer()->create())
            ->post(route('tickets.store'), ['subject' => 'Help', 'description' => 'Please']);

        Notification::assertSentTo([$admin, $agent], TicketCreated::class);
        Notification::assertNotSentTo($otherCustomer, TicketCreated::class);
    }

    public function test_a_staff_reply_emails_the_customer(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs(User::factory()->agent()->create())
            ->post(route('tickets.reply', $ticket->reference), ['body' => 'Fixed it.']);

        Notification::assertSentTo($ticket->requester, TicketReplied::class);
    }

    public function test_a_customer_reply_emails_the_assignee(): void
    {
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create(['assignee_id' => $agent->id]);

        $this->actingAs($ticket->requester)
            ->post(route('tickets.reply', $ticket->reference), ['body' => 'Thanks!']);

        Notification::assertSentTo($agent, TicketReplied::class);
    }

    public function test_internal_notes_email_nobody(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs(User::factory()->agent()->create())
            ->post(route('tickets.reply', $ticket->reference), ['body' => 'Check logs', 'internal' => true]);

        Notification::assertNothingSent();
    }

    public function test_assigning_a_ticket_emails_the_assignee_unless_they_assigned_themselves(): void
    {
        $ticket = Ticket::factory()->create();
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->agent()->create();

        $this->actingAs($admin)->patch(route('tickets.update', $ticket->reference), ['assignee_id' => $agent->id]);
        $this->actingAs($agent)->patch(route('tickets.update', $ticket->reference), ['assignee_id' => null]);
        $this->actingAs($agent)->patch(route('tickets.update', $ticket->reference), ['assignee_id' => $agent->id]);

        Notification::assertSentToTimes($agent, TicketAssigned::class, 1);
    }

    public function test_emails_link_to_the_ticket_on_the_organizations_subdomain(): void
    {
        $ticket = Ticket::factory()->create(['reference' => 'TKT-1234']);
        $host = parse_url(config('app.url'), PHP_URL_HOST);

        $mail = (new TicketCreated($ticket))->toMail($ticket->requester);

        $this->assertStringContainsString("://{$ticket->organization->slug}.{$host}", (string) $mail->actionUrl);
        $this->assertStringEndsWith('/tickets/TKT-1234', (string) $mail->actionUrl);
    }
}
