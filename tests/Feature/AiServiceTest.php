<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    public function test_health_passes_when_the_service_and_ollama_are_up(): void
    {
        Http::fake(['*/health' => Http::response(['status' => 'ok', 'ollama' => true])]);

        $this->artisan('ai:health')->assertSuccessful();
    }

    public function test_health_fails_when_ollama_is_down(): void
    {
        Http::fake(['*/health' => Http::response(['status' => 'ok', 'ollama' => false])]);

        $this->artisan('ai:health')->assertFailed();
    }

    public function test_health_fails_when_the_service_errors(): void
    {
        Http::fake(['*/health' => Http::response(status: 500)]);

        $this->artisan('ai:health')->expectsOutputToContain('returned HTTP 500')->assertFailed();
    }

    public function test_health_fails_when_the_service_is_unreachable(): void
    {
        Http::fake(['*/health' => Http::failedConnection()]);

        $this->artisan('ai:health')->expectsOutputToContain('unreachable')->assertFailed();
    }
}
