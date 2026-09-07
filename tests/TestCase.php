<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests run against a real MariaDB database (SQLite cannot parse the raw
     * MySQL DDL in our migrations, and the reports depend on MariaDB JSON
     * functions). RefreshDatabase therefore drops and rebuilds every table it
     * touches, so a misconfigured DB_DATABASE would destroy the development
     * database.
     *
     * This runs inside parent::setUp() *before* setUpTraits() boots
     * RefreshDatabase, so an unsafe target aborts before any table is dropped.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if (! str_ends_with((string) $database, '_test')) {
            throw new RuntimeException(
                "Refusing to run tests against database [{$database}] on connection "
                ."[{$connection}]: the test database name must end in \"_test\". "
                .'Check DB_DATABASE in phpunit.xml.'
            );
        }
    }
}
