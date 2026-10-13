<?php

namespace Tests;

use App\Models\Organization;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Boot the app, then refuse to continue unless it points at a test database.
     *
     * Runs before traits like RefreshDatabase, so a misconfigured environment
     * can never wipe development data. (A trait method would override a guard
     * placed in beforeRefreshingDatabase(), so it lives here instead.)
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $database = config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with((string) $database, '_test')) {
            throw new RuntimeException("Refusing to run tests against non-test database [{$database}].");
        }
    }

    /**
     * Tests never call real HTTP services (e.g. Ollama); fake them explicitly.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * Answer embedding requests to Ollama with the given vector.
     *
     * @param  list<float>  $values
     */
    protected function fakeEmbeddings(array $values = [1.0]): void
    {
        Http::fake(['*/api/embed' => Http::response(['embeddings' => [$this->vector($values)]])]);
    }

    /**
     * A 1024-dimension vector starting with the given values, padded with zeros.
     *
     * @param  list<float>  $values
     * @return list<float>
     */
    protected function vector(array $values): array
    {
        return array_pad($values, 1024, 0.0);
    }

    /**
     * Make a new organization current and send requests to its subdomain.
     */
    protected function inOrganization(): Organization
    {
        $organization = Organization::factory()->create()->makeCurrent();

        URL::forceRootUrl('http://'.$organization->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST));

        return $organization;
    }
}
