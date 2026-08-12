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

    public function test_manual_fiscal_operation_migration_rolls_back_and_reapplies(): void
    {
        Artisan::call('migrate', [
            '--database' => 'finance_fiscal',
            '--path' => 'database/migrations/finance_fiscal',
            '--force' => true,
        ]);

        $migration = require database_path('migrations/finance_fiscal/2026_08_12_150000_add_manual_operation_to_fiscal_documents.php');
        $this->assertTrue(Schema::connection('finance_fiscal')->hasColumns('fiscal_documents', ['emission_origin', 'access_key', 'registered_by_user_id', 'manual_authorization_notes']));
        $migration->down();
        $this->assertFalse(Schema::connection('finance_fiscal')->hasColumn('fiscal_documents', 'emission_origin'));
        $this->assertFalse(Schema::connection('finance_fiscal')->hasColumn('fiscal_artifacts', 'original_filename'));
        $migration->up();
        $this->assertTrue(Schema::connection('finance_fiscal')->hasColumn('fiscal_documents', 'emission_origin'));
        $this->assertTrue(Schema::connection('finance_fiscal')->hasColumn('fiscal_artifacts', 'original_filename'));
    }
}
