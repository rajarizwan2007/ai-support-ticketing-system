<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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
}
