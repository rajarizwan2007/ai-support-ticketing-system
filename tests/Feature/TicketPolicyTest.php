<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inOrganization();
        $this->ticket = Ticket::factory()->create();
    }

    public function test_admins_can_view_update_and_delete(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->can('view', $this->ticket));
        $this->assertTrue($admin->can('update', $this->ticket));
        $this->assertTrue($admin->can('delete', $this->ticket));
    }

    public function test_agents_can_view_and_update_but_not_delete(): void
    {
        $agent = User::factory()->agent()->create();

        $this->assertTrue($agent->can('view', $this->ticket));
        $this->assertTrue($agent->can('update', $this->ticket));
        $this->assertFalse($agent->can('delete', $this->ticket));
    }

    public function test_customers_can_only_view_their_own_tickets(): void
    {
        $requester = $this->ticket->requester;
        $otherCustomer = User::factory()->customer()->create();

        $this->assertTrue($requester->can('view', $this->ticket));
        $this->assertFalse($otherCustomer->can('view', $this->ticket));
        $this->assertFalse($requester->can('update', $this->ticket));
        $this->assertFalse($requester->can('delete', $this->ticket));
    }
}
