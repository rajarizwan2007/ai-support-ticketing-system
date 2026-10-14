<?php

namespace Tests\Feature;

use App\Jobs\EmbedModel;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SimilarTicketsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inOrganization();
    }

    public function test_creating_a_ticket_queues_its_embedding(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->customer()->create())
            ->post(route('tickets.store'), ['subject' => 'Help', 'description' => 'Please']);

        Queue::assertPushed(EmbedModel::class);
    }

    public function test_the_embed_job_stores_the_vector_and_model(): void
    {
        $this->fakeEmbeddings([0.6, 0.8]);
        $ticket = Ticket::factory()->create(['subject' => 'Cannot log in', 'description' => 'Page reloads.']);

        (new EmbedModel($ticket))->handle();

        $ticket->refresh();
        $this->assertEquals($this->vector([0.6, 0.8]), $ticket->embedding);
        $this->assertSame('qwen3-embedding:0.6b', $ticket->embedding_model);
        Http::assertSent(fn (Request $request) => $request->url() === 'http://ai:8000/embed'
            && $request['texts'] === ["Cannot log in\n\nPage reloads."]);
    }

    public function test_agents_see_similar_tickets_closest_first(): void
    {
        $ticket = Ticket::factory()->create(['embedding' => $this->vector([1, 0])]);
        Ticket::factory()->create(['reference' => 'TKT-2', 'embedding' => $this->vector([0.6, 0.8])]);
        Ticket::factory()->create(['reference' => 'TKT-1', 'embedding' => $this->vector([0.9, 0.1])]);
        Ticket::factory()->create(['reference' => 'TKT-UNRELATED', 'embedding' => $this->vector([0, 1])]);
        Ticket::factory()->create(['reference' => 'TKT-NO-EMBEDDING']);
        Ticket::factory()->for(Organization::factory())->create(['reference' => 'TKT-OTHER-ORG', 'embedding' => $this->vector([1, 0])]);

        $this->actingAs(User::factory()->agent()->create())
            ->get(route('tickets.show', $ticket->reference))
            ->assertInertia(fn (Assert $page) => $page
                ->has('similarTickets', 2)
                ->where('similarTickets.0.reference', 'TKT-1')
                ->where('similarTickets.1.reference', 'TKT-2')
                ->missing('ticket.embedding'));
    }

    public function test_customers_do_not_see_similar_tickets(): void
    {
        $customer = User::factory()->customer()->create();
        $ticket = Ticket::factory()->create(['requester_id' => $customer->id, 'embedding' => $this->vector([1])]);
        Ticket::factory()->create(['embedding' => $this->vector([1])]);

        $this->actingAs($customer)
            ->get(route('tickets.show', $ticket->reference))
            ->assertInertia(fn (Assert $page) => $page->has('similarTickets', 0));
    }
}
