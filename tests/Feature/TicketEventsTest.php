<?php

namespace Tests\Feature;

use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\TicketReplied;
use App\Events\TicketStatusChanged;
use App\Listeners\QueueTicketEmbedding;
use App\Listeners\SendAssignmentNotification;
use App\Listeners\SendNewTicketNotification;
use App\Listeners\SendReplyNotification;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The actions announce what happened; listeners do the side effects
 * (emails, embeddings). TicketNotificationsTest checks who gets each email.
 */
class TicketEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inOrganization();
    }

    public function test_listeners_are_registered_for_each_event(): void
    {
        $this->fakeTicketEvents();

        Event::assertListening(TicketCreated::class, SendNewTicketNotification::class);
        Event::assertListening(TicketCreated::class, QueueTicketEmbedding::class);
        Event::assertListening(TicketReplied::class, SendReplyNotification::class);
        Event::assertListening(TicketAssigned::class, SendAssignmentNotification::class);
    }

    public function test_ticket_actions_dispatch_events(): void
    {
        $this->fakeTicketEvents();
        $customer = User::factory()->customer()->create();
        $agent = User::factory()->agent()->create();

        $this->actingAs($customer)->post(route('tickets.store'), ['subject' => 'Help', 'description' => 'Please']);
        $ticket = Ticket::sole();
        $this->post(route('tickets.reply', $ticket->reference), ['body' => 'Any update?']);
        $this->actingAs($agent)->patch(route('tickets.update', $ticket->reference), ['status' => 'pending', 'assignee_id' => $agent->id]);

        Event::assertDispatched(TicketCreated::class, fn (TicketCreated $event) => $event->ticket->is($ticket));
        Event::assertDispatched(TicketReplied::class, fn (TicketReplied $event) => $event->message->body === 'Any update?');
        Event::assertDispatched(TicketStatusChanged::class, fn (TicketStatusChanged $event) => $event->changedBy->is($agent));
        Event::assertDispatched(TicketAssigned::class, fn (TicketAssigned $event) => $event->assignedBy->is($agent));
    }

    public function test_unchanged_fields_and_unassigning_dispatch_no_events(): void
    {
        $agent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->create(['assignee_id' => $agent->id]);
        $this->fakeTicketEvents();

        $this->actingAs($agent)->patch(route('tickets.update', $ticket->reference), ['status' => 'open', 'assignee_id' => null]);

        Event::assertNotDispatched(TicketStatusChanged::class);
        Event::assertNotDispatched(TicketAssigned::class);
    }

    public function test_email_listeners_run_on_the_queue(): void
    {
        $this->fakeEmbeddings();
        Queue::fake();

        $this->actingAs(User::factory()->customer()->create())
            ->post(route('tickets.store'), ['subject' => 'Help', 'description' => 'Please']);

        Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === SendNewTicketNotification::class);
        Queue::assertNotPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === QueueTicketEmbedding::class);
    }

    /**
     * Fake only the ticket events: a bare Event::fake() also fakes Eloquent
     * model events, so the tenant trait would not fill organization_id.
     */
    private function fakeTicketEvents(): void
    {
        Event::fake([TicketCreated::class, TicketReplied::class, TicketStatusChanged::class, TicketAssigned::class]);
    }
}
