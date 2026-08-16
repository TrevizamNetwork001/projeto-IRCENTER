<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiClientMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_clients_migration_can_roll_back_and_run(): void
    {
        $migration = require database_path(
            'migrations/2026_08_16_150000_create_api_clients_table.php'
        );

        $this->assertTrue(Schema::hasTable('api_clients'));

        $migration->down();
        $this->assertFalse(Schema::hasTable('api_clients'));

        $migration->up();
        $this->assertTrue(Schema::hasColumns('api_clients', [
            'id',
            'name',
            'identifier',
            'token_hash',
            'token_prefix',
            'is_active',
            'expires_at',
            'last_used_at',
            'last_used_ip',
            'revoked_at',
            'created_at',
            'updated_at',
        ]));
    }
}
