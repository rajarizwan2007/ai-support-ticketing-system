<?php

namespace Tests\Feature;

use App\Enums\MessageType;
use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Message;
use App\Models\Organization;
use App\Models\SlaPolicy;
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

    public function test_agents_can_change_the_priority_and_it_is_logged(): void
    {
        $ticket = Ticket::factory()->create(['priority' => 'low']);

        $this->actingAs(User::factory()->agent()->create())
            ->patch(route('tickets.update', $ticket->reference), ['priority' => 'urgent'])
            ->assertSessionHasNoErrors();

        $this->assertSame(Priority::Urgent, $ticket->fresh()->priority);
        $this->assertStringStartsWith('Priority changed to urgent', $ticket->messages()->sole()->body);
    }

    public function test_agents_can_assign_and_unassign_a_ticket(): void
    {
        $ticket = Ticket::factory()->create();
        $agent = User::factory()->agent()->create(['name' => 'Sam Agent']);
        $this->actingAs($agent);

        $this->patch(route('tickets.update', $ticket->reference), ['assignee_id' => $agent->id]);
        $this->assertSame($agent->id, $ticket->fresh()->assignee_id);

        $this->patch(route('tickets.update', $ticket->reference), ['assignee_id' => null]);
        $this->assertNull($ticket->fresh()->assignee_id);

        $this->assertSame(
            ['Assigned to Sam Agent by Sam Agent.', 'Unassigned by Sam Agent.'],
            $ticket->messages()->oldest('id')->pluck('body')->all(),
        );
    }

    public function test_tickets_cannot_be_assigned_to_customers(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs(User::factory()->agent()->create())
            ->patch(route('tickets.update', $ticket->reference), ['assignee_id' => $ticket->requester_id])
            ->assertSessionHasErrors('assignee_id');
    }

    public function test_tickets_cannot_be_assigned_to_another_organizations_agent(): void
    {
        $ticket = Ticket::factory()->create();
        $otherAgent = User::factory()->agent()->for(Organization::factory())->create();

        $this->actingAs(User::factory()->agent()->create())
            ->patch(route('tickets.update', $ticket->reference), ['assignee_id' => $otherAgent->id])
            ->assertSessionHasErrors('assignee_id');
    }

    public function test_the_first_public_agent_reply_records_the_first_response(): void
    {
        $customer = User::factory()->customer()->create();
        $ticket = Ticket::factory()->create(['requester_id' => $customer->id]);
        $agent = User::factory()->agent()->create();

        $this->actingAs($customer)->post(route('tickets.reply', $ticket->reference), ['body' => 'Hello?']);
        $this->actingAs($agent)->post(route('tickets.reply', $ticket->reference), ['body' => 'Note', 'internal' => true]);
        $this->assertNull($ticket->fresh()->first_responded_at);

        $this->travelTo(now()->startOfMinute());
        $this->actingAs($agent)->post(route('tickets.reply', $ticket->reference), ['body' => 'On it.']);
        $this->assertEquals(now(), $ticket->fresh()->first_responded_at);

        $this->travel(1)->hour();
        $this->actingAs($agent)->post(route('tickets.reply', $ticket->reference), ['body' => 'Fixed.']);
        $this->assertEquals(now()->subHour(), $ticket->fresh()->first_responded_at);
    }

    public function test_resolving_closing_and_reopening_set_the_sla_timestamps(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'open']);
        $this->actingAs(User::factory()->agent()->create());
        $this->travelTo(now()->startOfMinute());

        $this->patch(route('tickets.update', $ticket->reference), ['status' => 'resolved']);
        $this->assertEquals(now(), $ticket->fresh()->resolved_at);
        $this->assertNull($ticket->fresh()->closed_at);

        $this->travel(1)->hour();
        $this->patch(route('tickets.update', $ticket->reference), ['status' => 'closed']);
        $this->assertEquals(now()->subHour(), $ticket->fresh()->resolved_at);
        $this->assertEquals(now(), $ticket->fresh()->closed_at);

        $this->patch(route('tickets.update', $ticket->reference), ['status' => 'open']);
        $this->assertNull($ticket->fresh()->resolved_at);
        $this->assertNull($ticket->fresh()->closed_at);
    }

    public function test_customers_can_open_a_ticket_with_sla_due_dates(): void
    {
        $customer = User::factory()->customer()->create();
        $sla = SlaPolicy::factory()->priority(Priority::High)->create();
        SlaPolicy::factory()->priority(Priority::High)->create(['is_active' => false, 'first_response_minutes' => 1]);
        $category = Category::factory()->create();
        Ticket::factory()->create(['reference' => 'TKT-1001']);
        $this->travelTo(now()->startOfMinute());

        $this->actingAs($customer)
            ->post(route('tickets.store'), [
                'subject' => 'Cannot log in',
                'description' => 'Password reset email never arrives.',
                'category_id' => $category->id,
                'priority' => 'high',
            ])
            ->assertRedirect(route('tickets.show', 'TKT-1002'));

        $ticket = Ticket::where('reference', 'TKT-1002')->sole();
        $this->assertSame($customer->id, $ticket->requester_id);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertSame($sla->id, $ticket->sla_policy_id);
        $this->assertEquals(now()->addMinutes($sla->first_response_minutes), $ticket->first_response_due_at);
        $this->assertEquals(now()->addMinutes($sla->resolution_minutes), $ticket->resolution_due_at);
    }

    public function test_a_ticket_without_a_matching_sla_policy_has_no_due_dates(): void
    {
        $this->actingAs(User::factory()->customer()->create())
            ->post(route('tickets.store'), ['subject' => 'Question', 'description' => 'How do I export?']);

        $ticket = Ticket::sole();
        $this->assertSame(Priority::Medium, $ticket->priority);
        $this->assertNull($ticket->sla_policy_id);
        $this->assertNull($ticket->first_response_due_at);
    }

    public function test_a_ticket_cannot_use_another_organizations_category(): void
    {
        $otherCategory = Category::factory()->for(Organization::factory())->create();

        $this->actingAs(User::factory()->customer()->create())
            ->post(route('tickets.store'), ['subject' => 'Hi', 'description' => 'Hi', 'category_id' => $otherCategory->id])
            ->assertSessionHasErrors('category_id');
    }

    public function test_the_create_page_lists_the_organizations_categories(): void
    {
        Category::factory()->count(2)->create();

        $this->actingAs(User::factory()->customer()->create())
            ->get(route('tickets.create'))
            ->assertInertia(fn (Assert $page) => $page->component('tickets/create')->has('categories', 2));
    }

    public function test_changing_the_priority_recalculates_the_sla_due_dates(): void
    {
        $this->travelTo(now()->startOfMinute());
        $urgent = SlaPolicy::factory()->priority(Priority::Urgent)->create();
        $ticket = Ticket::factory()->create([
            'priority' => 'low',
            'created_at' => now()->subHour(),
            'first_response_due_at' => now()->addDay(),
            'resolution_due_at' => now()->addWeek(),
        ]);

        $this->actingAs(User::factory()->agent()->create())
            ->patch(route('tickets.update', $ticket->reference), ['priority' => 'urgent']);

        $ticket->refresh();
        $this->assertSame($urgent->id, $ticket->sla_policy_id);
        $this->assertEquals(now()->subHour()->addMinutes($urgent->first_response_minutes), $ticket->first_response_due_at);
        $this->assertEquals(now()->subHour()->addMinutes($urgent->resolution_minutes), $ticket->resolution_due_at);
    }

    public function test_the_list_can_be_searched_by_reference_or_subject(): void
    {
        Ticket::factory()->create(['reference' => 'TKT-5001', 'subject' => 'Printer on fire']);
        Ticket::factory()->create(['reference' => 'TKT-5002', 'subject' => 'Refund request']);
        $this->actingAs(User::factory()->agent()->create());

        $this->get(route('tickets.index', ['search' => 'printer']))
            ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.reference', 'TKT-5001'));

        $this->get(route('tickets.index', ['search' => 'tkt-5002']))
            ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.reference', 'TKT-5002'));
    }

    public function test_the_list_can_be_filtered_by_status_priority_and_assignee(): void
    {
        $agent = User::factory()->agent()->create();
        Ticket::factory()->create(['status' => 'open', 'priority' => 'high', 'assignee_id' => $agent->id]);
        Ticket::factory()->create(['status' => 'open', 'priority' => 'low', 'assignee_id' => null]);
        Ticket::factory()->create(['status' => 'closed', 'priority' => 'high', 'assignee_id' => null]);
        $this->actingAs($agent);

        $this->get(route('tickets.index', ['status' => 'open']))->assertInertia(fn (Assert $page) => $page->has('tickets.data', 2));
        $this->get(route('tickets.index', ['priority' => 'high']))->assertInertia(fn (Assert $page) => $page->has('tickets.data', 2));
        $this->get(route('tickets.index', ['assignee' => $agent->id]))->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1));
        $this->get(route('tickets.index', ['assignee' => 'unassigned']))->assertInertia(fn (Assert $page) => $page->has('tickets.data', 2));
        $this->get(route('tickets.index', ['status' => 'open', 'priority' => 'high']))->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1));
        $this->get(route('tickets.index', ['status' => 'exploded']))->assertInertia(fn (Assert $page) => $page->has('tickets.data', 3));
    }

    public function test_searching_does_not_show_customers_other_peoples_tickets(): void
    {
        $customer = User::factory()->customer()->create();
        Ticket::factory()->create(['requester_id' => $customer->id, 'subject' => 'Login broken']);
        Ticket::factory()->create(['subject' => 'Login broken too']);

        $this->actingAs($customer)
            ->get(route('tickets.index', ['search' => 'login']))
            ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->has('staff', 0));
    }

    public function test_a_customer_reply_reopens_a_resolved_ticket(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Pat Customer']);
        $ticket = Ticket::factory()->create(['requester_id' => $customer->id, 'status' => 'resolved', 'resolved_at' => now()]);

        $this->actingAs($customer)->post(route('tickets.reply', $ticket->reference), ['body' => 'Still broken.']);

        $ticket->refresh();
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertSame('Status changed to open by Pat Customer.', $ticket->messages()->where('type', MessageType::System)->sole()->body);
    }

    public function test_replies_that_should_not_reopen_a_ticket_leave_the_status_alone(): void
    {
        $customer = User::factory()->customer()->create();
        $onHold = Ticket::factory()->create(['requester_id' => $customer->id, 'status' => 'on_hold']);
        $resolved = Ticket::factory()->create(['status' => 'resolved']);

        $this->actingAs($customer)->post(route('tickets.reply', $onHold->reference), ['body' => 'Any news?']);
        $this->actingAs(User::factory()->agent()->create())->post(route('tickets.reply', $resolved->reference), ['body' => 'Glad it works.']);

        $this->assertSame(TicketStatus::OnHold, $onHold->fresh()->status);
        $this->assertSame(TicketStatus::Resolved, $resolved->fresh()->status);
    }
}
