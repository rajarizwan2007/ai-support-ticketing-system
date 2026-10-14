<?php

namespace Tests\Feature;

use App\Enums\MessageType;
use App\Enums\SlaTarget;
use App\Enums\TicketStatus;
use App\Jobs\EscalateTicket;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\SlaBreached;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SlaBreachTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_escalates_tickets_past_their_first_response_deadline(): void
    {
        Queue::fake();
        $this->inOrganization();
        $ticket = Ticket::factory()->create(['first_response_due_at' => now()->subMinute()]);

        $this->artisan('tickets:check-sla')->assertSuccessful();

        Queue::assertPushed(EscalateTicket::class, fn (EscalateTicket $job) => $job->ticket->is($ticket) && $job->target === SlaTarget::FirstResponse);
        $this->assertNotNull($ticket->fresh()->first_response_breached_at);
    }

    public function test_it_escalates_tickets_past_their_resolution_deadline(): void
    {
        Queue::fake();
        $this->inOrganization();
        $ticket = Ticket::factory()->create(['first_responded_at' => now()->subHours(2), 'resolution_due_at' => now()->subMinute()]);

        $this->artisan('tickets:check-sla')->assertSuccessful();

        Queue::assertPushed(EscalateTicket::class, fn (EscalateTicket $job) => $job->target === SlaTarget::Resolution);
        Queue::assertPushedTimes(EscalateTicket::class, 1);
        $this->assertNotNull($ticket->fresh()->resolution_breached_at);
    }

    public function test_it_skips_tickets_that_are_on_time_met_resolved_or_already_escalated(): void
    {
        Queue::fake();
        $this->inOrganization();
        $past = now()->subMinute();
        Ticket::factory()->create(['first_response_due_at' => now()->addHour()]);
        Ticket::factory()->create(['first_response_due_at' => $past, 'first_responded_at' => now()->subHours(2)]);
        Ticket::factory()->create(['first_response_due_at' => $past, 'status' => TicketStatus::Resolved]);
        Ticket::factory()->create(['first_response_due_at' => $past, 'status' => TicketStatus::Closed]);
        Ticket::factory()->create(['first_response_due_at' => $past])->forceFill(['first_response_breached_at' => $past])->save();

        $this->artisan('tickets:check-sla')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_a_breach_is_escalated_only_once(): void
    {
        Queue::fake();
        $this->inOrganization();
        Ticket::factory()->create(['first_response_due_at' => now()->subMinute()]);

        $this->artisan('tickets:check-sla');
        $this->artisan('tickets:check-sla');

        Queue::assertPushedTimes(EscalateTicket::class, 1);
    }

    public function test_it_checks_every_organization_and_leaves_none_current(): void
    {
        Queue::fake();
        foreach (Organization::factory()->count(2)->create() as $organization) {
            $organization->makeCurrent();
            Ticket::factory()->create(['first_response_due_at' => now()->subMinute()]);
        }
        Organization::forgetCurrent();

        $this->artisan('tickets:check-sla')->assertSuccessful();

        Queue::assertPushedTimes(EscalateTicket::class, 2);
        $this->assertNull(Organization::currentId());
    }

    public function test_a_new_deadline_after_a_priority_change_can_be_escalated_again(): void
    {
        $this->inOrganization();
        $ticket = Ticket::factory()->create(['priority' => 'low', 'first_response_due_at' => now()->subMinute()]);
        $ticket->forceFill(['first_response_breached_at' => now()])->save();

        $ticket->update(['priority' => 'urgent']);

        $this->assertNull($ticket->fresh()->first_response_breached_at);
    }

    public function test_the_escalation_logs_the_breach_and_alerts_admins_and_the_assignee(): void
    {
        Notification::fake();
        $this->inOrganization();
        $admin = User::factory()->admin()->create();
        $otherAgent = User::factory()->agent()->create();
        $ticket = Ticket::factory()->assigned()->create(['first_response_due_at' => now()->subMinute()]);

        (new EscalateTicket($ticket, SlaTarget::FirstResponse))->handle();

        Notification::assertSentTo([$admin, $ticket->assignee], SlaBreached::class);
        Notification::assertNotSentTo([$otherAgent, $ticket->requester], SlaBreached::class);
        $message = $ticket->messages()->sole();
        $this->assertSame(MessageType::System, $message->type);
        $this->assertStringStartsWith('First response SLA breached', $message->body);
    }

    public function test_the_check_is_scheduled_every_five_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'tickets:check-sla'));

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
    }
}
