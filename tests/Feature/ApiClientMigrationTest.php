<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiClientMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scopes_migration_can_roll_back_and_run(): void
    {
        $migration = require database_path(
            'migrations/2026_08_16_160000_add_scopes_to_api_clients_table.php'
        );

        $this->assertTrue(Schema::hasTable('api_clients'));
        $this->assertTrue(Schema::hasColumn('api_clients', 'scopes'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('api_clients', 'scopes'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('api_clients', 'scopes'));
    }
}
