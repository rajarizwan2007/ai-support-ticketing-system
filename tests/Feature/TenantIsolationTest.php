<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * An acme admin, signed in on acme's subdomain, must never reach globex's tickets.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $acme;

    private Organization $globex;

    private User $acmeAdmin;

    private Ticket $globexTicket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->globex = Organization::factory()->create()->makeCurrent();
        $this->globexTicket = Ticket::factory()->create(['reference' => 'TKT-2000']);

        $this->acme = $this->inOrganization();
        $this->acmeAdmin = User::factory()->admin()->create();
        Ticket::factory()->create(['reference' => 'TKT-1000']);
    }

    public function test_the_list_only_shows_the_current_organizations_tickets(): void
    {
        $this->actingAs($this->acmeAdmin)
            ->get(route('tickets.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('tickets.data', 1)
                ->where('tickets.data.0.reference', 'TKT-1000'));
    }

    public function test_another_organizations_ticket_cannot_be_opened_replied_to_or_updated(): void
    {
        $this->actingAs($this->acmeAdmin);

        $this->get(route('tickets.show', 'TKT-2000'))->assertNotFound();
        $this->post(route('tickets.reply', 'TKT-2000'), ['body' => 'Hi'])->assertNotFound();
        $this->patch(route('tickets.update', 'TKT-2000'), ['status' => 'closed'])->assertNotFound();

        $this->globex->makeCurrent();
        $this->assertSame(TicketStatus::Open, $this->globexTicket->fresh()->status);
        $this->assertSame(0, $this->globexTicket->messages()->count());
    }

    public function test_the_same_reference_in_two_organizations_opens_each_ones_own_ticket(): void
    {
        $this->globex->makeCurrent();
        Ticket::factory()->create(['reference' => 'TKT-1000', 'subject' => 'Globex ticket']);
        $this->acme->makeCurrent();

        $this->actingAs($this->acmeAdmin)
            ->get(route('tickets.show', 'TKT-1000'))
            ->assertInertia(fn (Assert $page) => $page->where('ticket.organization_id', $this->acme->id));
    }
}
