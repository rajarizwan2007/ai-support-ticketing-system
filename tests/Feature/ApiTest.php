<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $globex;

    protected function setUp(): void
    {
        parent::setUp();

        $this->globex = Organization::factory()->create();
        $this->inOrganization();
    }

    public function test_users_log_in_for_a_token_that_works_on_their_subdomain(): void
    {
        $user = User::factory()->customer()->create();

        $token = $this->postJson(route('api.tokens.store'), ['email' => $user->email, 'password' => 'password', 'device_name' => 'cli'])
            ->assertCreated()
            ->json('token');

        $this->withToken($token)->getJson(route('api.tickets.index'))->assertOk();
    }

    public function test_wrong_passwords_and_other_organizations_users_get_no_token(): void
    {
        $user = User::factory()->customer()->create();
        $this->globex->makeCurrent();
        $globexUser = User::factory()->customer()->create();

        $this->postJson(route('api.tokens.store'), ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'cli'])
            ->assertUnprocessable();
        $this->postJson(route('api.tokens.store'), ['email' => $globexUser->email, 'password' => 'password', 'device_name' => 'cli'])
            ->assertUnprocessable();
    }

    public function test_requests_without_a_token_are_rejected(): void
    {
        $this->getJson(route('api.tickets.index'))->assertUnauthorized();
    }

    public function test_a_token_from_another_organization_is_rejected(): void
    {
        $this->globex->makeCurrent();
        $token = User::factory()->admin()->create()->createToken('cli')->plainTextToken;

        $this->withToken($token)->getJson(route('api.tickets.index'))->assertUnauthorized();
    }

    public function test_customers_list_only_their_own_tickets_without_internal_fields(): void
    {
        $customer = User::factory()->customer()->create();
        Ticket::factory()->create(['requester_id' => $customer->id, 'reference' => 'TKT-1001']);
        Ticket::factory()->create();
        Sanctum::actingAs($customer);

        $response = $this->getJson(route('api.tickets.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'TKT-1001')
            ->assertJsonPath('meta.per_page', 20);

        $this->assertArrayNotHasKey('organization_id', $response->json('data.0'));
        $this->assertArrayNotHasKey('embedding', $response->json('data.0'));
    }

    public function test_customers_cannot_open_other_tickets_or_see_internal_notes(): void
    {
        $customer = User::factory()->customer()->create();
        $ticket = Ticket::factory()->create(['requester_id' => $customer->id, 'reference' => 'TKT-1001']);
        Message::factory()->for($ticket)->create(['body' => 'Public reply']);
        Message::factory()->for($ticket)->internalNote()->create();
        $otherTicket = Ticket::factory()->create();
        Sanctum::actingAs($customer);

        $this->getJson(route('api.tickets.show', 'TKT-1001'))
            ->assertOk()
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.body', 'Public reply');
        $this->getJson(route('api.tickets.show', $otherTicket->reference))->assertForbidden();
    }

    public function test_staff_see_internal_notes(): void
    {
        $ticket = Ticket::factory()->create();
        Message::factory()->for($ticket)->internalNote()->create();
        Sanctum::actingAs(User::factory()->agent()->create());

        $this->getJson(route('api.tickets.show', $ticket->reference))
            ->assertJsonPath('data.messages.0.type', 'internal_note');
    }

    public function test_customers_create_tickets_and_reply(): void
    {
        $this->fakeEmbeddings();
        Sanctum::actingAs(User::factory()->customer()->create());

        $reference = $this->postJson(route('api.tickets.store'), ['subject' => 'Login broken', 'description' => 'I cannot log in.'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->json('data.reference');

        $this->postJson(route('api.tickets.reply', $reference), ['body' => 'Still broken.', 'internal' => true])
            ->assertCreated()
            ->assertJsonPath('data.type', 'reply');
    }

    public function test_validation_errors_are_json(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->postJson(route('api.tickets.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject', 'description']);
    }

    public function test_requests_are_limited_to_60_a_minute(): void
    {
        Sanctum::actingAs(User::factory()->customer()->create());

        for ($i = 0; $i < 60; $i++) {
            $this->getJson(route('api.tickets.index'))->assertOk();
        }

        $this->getJson(route('api.tickets.index'))->assertTooManyRequests();
    }

    public function test_users_can_revoke_their_token(): void
    {
        $user = User::factory()->customer()->create();
        $token = $user->createToken('cli')->plainTextToken;

        $this->withToken($token)->deleteJson(route('api.tokens.destroy'))->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }
}
