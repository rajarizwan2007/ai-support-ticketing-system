<?php

namespace Tests\Feature;

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Models\KbArticle;
use App\Models\Message;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_factory_keeps_requester_and_assignee_in_the_ticket_organization(): void
    {
        $organization = Organization::factory()->create();

        $ticket = Ticket::factory()->for($organization)->assigned()->create();

        $this->assertTrue($ticket->requester->organization->is($organization));
        $this->assertTrue($ticket->assignee->organization->is($organization));
        $this->assertTrue($ticket->assignee->hasRole('agent'));
        $this->assertSame(1, Organization::count());
    }

    public function test_ticket_attributes_are_cast_to_enums(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'on_hold']);

        $this->assertSame(TicketStatus::OnHold, $ticket->fresh()->status);
    }

    public function test_deleting_an_agent_unassigns_their_tickets_and_keeps_their_messages(): void
    {
        $ticket = Ticket::factory()->assigned()->create();
        $agent = $ticket->assignee;
        $message = Message::factory()->for($ticket)->create(['user_id' => $agent->id]);

        $agent->delete();

        $this->assertNull($ticket->fresh()->assignee_id);
        $this->assertNull($message->fresh()->user_id);
    }

    public function test_a_customer_with_tickets_cannot_be_deleted(): void
    {
        $ticket = Ticket::factory()->create();

        $this->expectException(QueryException::class);

        $ticket->requester->delete();
    }

    public function test_deleting_an_organization_removes_all_of_its_data(): void
    {
        $organization = Organization::factory()->create();
        $ticket = Ticket::factory()->for($organization)->assigned()->create();
        Message::factory()->for($ticket)->create();
        Message::factory()->for($ticket)->system()->create();
        KbArticle::factory()->for($organization)->published()->create();
        $otherTicket = Ticket::factory()->create();

        $organization->delete();

        $this->assertSame(0, User::where('organization_id', $organization->id)->count());
        $this->assertSame(0, Ticket::withTrashed()->where('organization_id', $organization->id)->count());
        $this->assertSame(0, Message::where('ticket_id', $ticket->id)->count());
        $this->assertSame(0, KbArticle::count());
        $this->assertTrue($otherTicket->fresh()->exists);
    }

    public function test_system_messages_have_no_author(): void
    {
        $message = Message::factory()->system()->create();

        $this->assertSame(MessageType::System, $message->type);
        $this->assertNull($message->author);
    }
}
