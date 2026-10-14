<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HorizonTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Horizon lists every organization's jobs, so a tenant admin must not open it.
     */
    public function test_tenant_admins_cannot_open_the_dashboard(): void
    {
        $this->inOrganization();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/horizon')
            ->assertForbidden();
    }

    /**
     * A job must finish before Redis hands it to another worker.
     */
    public function test_the_worker_timeout_is_below_the_queue_retry_window(): void
    {
        $this->assertLessThan(config('queue.connections.redis.retry_after'), config('horizon.defaults.supervisor-1.timeout'));
    }
}
