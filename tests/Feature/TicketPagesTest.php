<?php

namespace Tests\Feature;

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
}
