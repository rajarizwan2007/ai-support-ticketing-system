<?php

namespace Tests\Feature;

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inOrganization();
    }

    public function test_agents_see_every_ticket_in_the_organization(): void
    {
        Ticket::factory()->count(2)->create();

        $this->actingAs(User::factory()->agent()->create())
            ->get(route('tickets.index'))
            ->assertInertia(fn (Assert $page) => $page->component('tickets/index')->has('tickets.data', 2));
    }

    public function test_customers_only_see_their_own_tickets(): void
    {
        $customer = User::factory()->customer()->create();
        Ticket::factory()->create(['requester_id' => $customer->id]);
        Ticket::factory()->create();

        $this->actingAs($customer)
            ->get(route('tickets.index'))
            ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1));
    }

    public function test_customers_cannot_open_someone_elses_ticket(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs(User::factory()->customer()->create())
            ->get(route('tickets.show', $ticket->reference))
            ->assertForbidden();
    }

    public function test_customers_do_not_see_internal_notes(): void
    {
        $customer = User::factory()->customer()->create();
        $ticket = Ticket::factory()->create(['requester_id' => $customer->id]);
        Message::factory()->for($ticket)->create();
        Message::factory()->for($ticket)->internalNote()->create();

        $this->actingAs($customer)
            ->get(route('tickets.show', $ticket->reference))
            ->assertInertia(fn (Assert $page) => $page->component('tickets/show')->has('messages', 1));
    }

    public function test_customers_can_reply_to_their_own_ticket(): void
    {
        $customer = User::factory()->customer()->create();
        $ticket = Ticket::factory()->create(['requester_id' => $customer->id]);

        $this->actingAs($customer)
            ->post(route('tickets.reply', $ticket->reference), ['body' => 'Any update?'])
            ->assertRedirect();

        $this->assertSame(MessageType::Reply, $ticket->messages()->sole()->type);
    }

    public function test_customers_cannot_reply_to_someone_elses_ticket(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs(User::factory()->customer()->create())
            ->post(route('tickets.reply', $ticket->reference), ['body' => 'Hello'])
            ->assertForbidden();
    }

    public function test_customers_cannot_post_internal_notes(): void
    {
        $customer = User::factory()->customer()->create();
        $ticket = Ticket::factory()->create(['requester_id' => $customer->id]);

        $this->actingAs($customer)
            ->post(route('tickets.reply', $ticket->reference), ['body' => 'Sneaky', 'internal' => true]);

        $this->assertSame(MessageType::Reply, $ticket->messages()->sole()->type);
    }

    public function test_agents_can_post_internal_notes(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs(User::factory()->agent()->create())
            ->post(route('tickets.reply', $ticket->reference), ['body' => 'Check billing', 'internal' => true]);

        $this->assertSame(MessageType::InternalNote, $ticket->messages()->sole()->type);
    }

    public function test_agents_can_change_the_status_and_it_is_logged(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs(User::factory()->agent()->create())
            ->patch(route('tickets.update', $ticket->reference), ['status' => 'resolved'])
            ->assertRedirect();

        $this->assertSame(TicketStatus::Resolved, $ticket->fresh()->status);
        $this->assertSame(MessageType::System, $ticket->messages()->sole()->type);
    }

    public function test_customers_cannot_change_the_status(): void
    {
        $customer = User::factory()->customer()->create();
        $ticket = Ticket::factory()->create(['requester_id' => $customer->id]);

        $this->actingAs($customer)
            ->patch(route('tickets.update', $ticket->reference), ['status' => 'closed'])
            ->assertForbidden();
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs(User::factory()->agent()->create())
            ->patch(route('tickets.update', $ticket->reference), ['status' => 'exploded'])
            ->assertSessionHasErrors('status');
    }

    public function test_setting_the_same_status_adds_no_message(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'open']);

        $this->actingAs(User::factory()->agent()->create())
            ->patch(route('tickets.update', $ticket->reference), ['status' => 'open']);

        $this->assertSame(0, $ticket->messages()->count());
    }
}
