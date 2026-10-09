<?php

namespace Tests\Feature;

use App\Enums\MessageType;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What each role can do, through real requests, on a ticket someone else requested.
 */
class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inOrganization();
        $this->ticket = Ticket::factory()->create();
    }

    /**
     * @return array<string, array{string, int, int, MessageType}>
     */
    public static function roles(): array
    {
        // role => [view status, change-status status, type a ticked "internal note" is saved as]
        return [
            'admin' => ['admin', 200, 302, MessageType::InternalNote],
            'agent' => ['agent', 200, 302, MessageType::InternalNote],
            'customer' => ['customer', 403, 403, MessageType::Reply],
        ];
    }

    #[DataProvider('roles')]
    public function test_role_permissions(string $role, int $viewStatus, int $updateStatus, MessageType $internalNoteSavedAs): void
    {
        $user = User::factory()->{$role}()->create();
        $this->actingAs($user);

        $this->get(route('tickets.show', $this->ticket->reference))->assertStatus($viewStatus);

        $this->patch(route('tickets.update', $this->ticket->reference), ['status' => 'resolved'])
            ->assertStatus($updateStatus);

        $ownTicket = Ticket::factory()->create(['requester_id' => $user->id]);
        $this->post(route('tickets.reply', $ownTicket->reference), ['body' => 'Note', 'internal' => true]);
        $this->assertSame($internalNoteSavedAs, $ownTicket->messages()->sole()->type);
    }
}
