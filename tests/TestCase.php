<?php

namespace Tests;

use App\Models\Organization;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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
     * Make a new organization current and send requests to its subdomain.
     */
    protected function inOrganization(): Organization
    {
        $organization = Organization::factory()->create()->makeCurrent();

        URL::forceRootUrl('http://'.$organization->slug.'.'.parse_url(config('app.url'), PHP_URL_HOST));

        return $organization;
    }
}
