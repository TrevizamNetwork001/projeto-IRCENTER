<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceFiscalDatabaseTest extends TestCase
{
    public function test_finance_migrations_use_dedicated_database(): void
    {
        Artisan::call('migrate', [
            '--database' => 'finance_fiscal',
            '--path' => 'database/migrations/finance_fiscal',
            '--force' => true,
        ]);

        $this->assertTrue(
            Schema::connection('finance_fiscal')
                ->hasTable('domain_audit_events')
        );

        $this->assertFalse(
            Schema::hasTable('domain_audit_events')
        );
    }

    public function test_domain_audit_table_has_expected_columns(): void
    {
        Artisan::call('migrate', [
            '--database' => 'finance_fiscal',
            '--path' => 'database/migrations/finance_fiscal',
            '--force' => true,
        ]);

        $this->assertTrue(
            Schema::connection('finance_fiscal')
                ->hasColumns(
                    'domain_audit_events',
                    [
                        'id',
                        'module',
                        'action',
                        'actor_user_id',
                        'entity_type',
                        'entity_id',
                        'correlation_id',
                        'metadata',
                        'ip_address',
                        'user_agent',
                        'created_at',
                    ]
                )
        );
    }
}
