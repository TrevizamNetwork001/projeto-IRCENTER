<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Fiscal\Contracts\NfseProvider;
use App\Modules\Shared\Contracts\ClientDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceFiscalFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_operations_are_disabled_by_default(): void
    {
        $this->assertFalse(
            config('finance_fiscal.finance.enabled')
        );

        $this->assertFalse(
            config(
                'finance_fiscal.finance.automation_enabled'
            )
        );

        $this->assertFalse(
            config(
                'finance_fiscal.finance.payment_live_enabled'
            )
        );

        $this->assertFalse(
            config('finance_fiscal.fiscal.enabled')
        );

        $this->assertFalse(
            config(
                'finance_fiscal.fiscal.transmission_enabled'
            )
        );

        $this->assertFalse(
            config(
                'finance_fiscal.fiscal.live_enabled'
            )
        );
    }

    public function test_fake_providers_are_the_defaults(): void
    {
        $payment = app(PaymentProvider::class);
        $nfse = app(NfseProvider::class);

        $this->assertSame('fake', $payment->key());
        $this->assertFalse($payment->isLive());

        $this->assertSame('fake', $nfse->key());
        $this->assertFalse($nfse->isLive());
    }

    public function test_finance_database_is_isolated_in_tests(): void
    {
        $this->assertSame(
            'sqlite',
            DB::connection('finance_fiscal')
                ->getDriverName()
        );

        $this->assertSame(
            ':memory:',
            config(
                'database.connections.finance_fiscal.database'
            )
        );
    }

    public function test_client_directory_returns_safe_snapshot(): void
    {
        $client = Client::factory()->create([
            'client_code' => 'CLI-000999',
            'contract_number' => 'CTR-LEGADO-999',
            'legal_name' => 'Empresa Teste LTDA',
            'trade_name' => 'Empresa Teste',
            'notes' => 'Informação interna.',
        ]);

        $snapshot = app(ClientDirectory::class)
            ->find($client->id);

        $this->assertNotNull($snapshot);
        $this->assertSame($client->id, $snapshot->id);
        $this->assertSame(
            'CLI-000999',
            $snapshot->clientCode
        );
        $this->assertSame(
            'Empresa Teste',
            $snapshot->displayName()
        );

        $this->assertFalse(
            property_exists($snapshot, 'notes')
        );

        $this->assertFalse(
            property_exists(
                $snapshot,
                'contractNumber'
            )
        );
    }

    public function test_client_directory_returns_null_when_missing(): void
    {
        $this->assertNull(
            app(ClientDirectory::class)->find(999999)
        );
    }
}
