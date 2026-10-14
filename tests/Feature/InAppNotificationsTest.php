<?php

namespace Tests\Feature;

use App\Enums\SlaTarget;
use App\Jobs\EscalateTicket;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InAppNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inOrganization();
        $this->agent = User::factory()->agent()->create();
        $this->ticket = Ticket::factory()->create(['reference' => 'TKT-1001']);
    }

    public function test_assignments_replies_and_breaches_are_stored_for_the_bell(): void
    {
        $admin = User::factory()->admin()->create();
        $this->ticket->update(['first_response_due_at' => now()->subMinute()]);

        (new EscalateTicket($this->ticket, SlaTarget::FirstResponse))->handle();
        $this->actingAs($admin)->patch(route('tickets.update', 'TKT-1001'), ['assignee_id' => $this->agent->id]);
        $this->actingAs($this->agent)->post(route('tickets.reply', 'TKT-1001'), ['body' => 'Looking into it.']);

        $this->assertStringContainsString('assigned TKT-1001 to you', $this->agent->notifications()->sole()->data['text']);
        $this->assertStringContainsString('replied on TKT-1001', $this->ticket->requester->notifications()->sole()->data['text']);
        $this->assertStringContainsString('missed its First response SLA deadline', $admin->notifications()->sole()->data['text']);
    }

    public function test_every_page_shares_the_unread_count_and_recent_notifications(): void
    {
        $this->notifyAgent();
        $this->notifyAgent()->markAsRead();

        $this->actingAs($this->agent)
            ->get(route('tickets.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.unreadCount', 1)
                ->has('notifications.recent', 2)
                ->where('notifications.recent.0.data.ticket_reference', 'TKT-1001'));
    }

    public function test_opening_a_notification_marks_it_read_and_shows_the_ticket(): void
    {
        $notification = $this->notifyAgent();

        $this->actingAs($this->agent)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('tickets.show', 'TKT-1001'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_users_cannot_open_someone_elses_notification(): void
    {
        $notification = $this->notifyAgent();

        $this->actingAs(User::factory()->agent()->create())
            ->post(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_only_touches_the_users_own_notifications(): void
    {
        $this->notifyAgent();
        $this->notifyAgent();
        $otherAgent = User::factory()->agent()->create();
        $otherAgent->notify(new TicketAssigned($this->ticket, $this->agent));

        $this->actingAs($this->agent)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $this->agent->unreadNotifications()->count());
        $this->assertSame(1, $otherAgent->unreadNotifications()->count());
    }

    private function notifyAgent(): DatabaseNotification
    {
        $this->agent->notify(new TicketAssigned($this->ticket, User::factory()->admin()->create()));

        return $this->agent->notifications()->latest()->firstOrFail();
    }
}
