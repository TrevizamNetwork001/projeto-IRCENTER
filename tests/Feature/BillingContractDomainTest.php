<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Modules\Finance\Actions\CreateBillingContract;
use App\Modules\Finance\Models\BillingContract;
use App\Modules\Shared\Models\DomainAuditEvent;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BillingContractDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--database' => 'finance_fiscal',
            '--path' => 'database/migrations/finance_fiscal',
            '--force' => true,
        ]);
    }

    public function test_contract_is_created_from_core_client_snapshot(): void
    {
        $client = Client::factory()->create([
            'client_code' => 'CLI-000500',
            'legal_name' => 'Empresa XYZ LTDA',
            'trade_name' => 'Empresa XYZ',
            'active' => true,
        ]);

        $contract = app(CreateBillingContract::class)
            ->handle(
                clientId: $client->id,
                attributes: [
                    'generation_day' => 5,
                    'due_day' => 20,
                ],
                items: [
                    [
                        'description' =>
                            'Gerenciamento de rede',
                        'quantity' => '1.0000',
                        'unit_amount' => '850.00',
                    ],
                ],
            );

        $this->assertSame(
            $client->id,
            $contract->core_client_id
        );

        $this->assertSame(
            'Empresa XYZ LTDA',
            $contract->client_legal_name_snapshot
        );

        $this->assertSame(
            'Empresa XYZ',
            $contract->client_trade_name_snapshot
        );

        $this->assertSame(5, $contract->generation_day);
        $this->assertSame(20, $contract->due_day);

        $this->assertSame(
            BillingContract::STATUS_DRAFT,
            $contract->status
        );

        $this->assertFalse($contract->auto_charge);
        $this->assertFalse($contract->send_email);

        $this->assertCount(1, $contract->items);

        $this->assertSame(
            '850.00',
            $contract->items->first()->unit_amount
        );
    }

    public function test_same_client_can_have_multiple_financial_contracts(): void
    {
        $client = Client::factory()->create([
            'active' => true,
        ]);

        $action = app(CreateBillingContract::class);

        foreach ([
            'Gerenciamento de rede',
            'Monitoramento',
        ] as $description) {
            $action->handle(
                clientId: $client->id,
                attributes: [],
                items: [
                    [
                        'description' => $description,
                        'unit_amount' => '100.00',
                    ],
                ],
            );
        }

        $this->assertSame(
            2,
            BillingContract::query()
                ->where(
                    'core_client_id',
                    $client->id
                )
                ->count()
        );
    }

    public function test_inactive_client_cannot_receive_contract(): void
    {
        $client = Client::factory()->create([
            'active' => false,
        ]);

        $this->expectException(
            DomainException::class
        );

        app(CreateBillingContract::class)
            ->handle(
                clientId: $client->id,
                attributes: [],
                items: [
                    [
                        'description' => 'Serviço',
                        'unit_amount' => '100.00',
                    ],
                ],
            );
    }

    public function test_contract_creation_is_audited(): void
    {
        $client = Client::factory()->create([
            'active' => true,
        ]);

        $contract = app(CreateBillingContract::class)
            ->handle(
                clientId: $client->id,
                attributes: [],
                items: [
                    [
                        'description' => 'Serviço',
                        'unit_amount' => '150.00',
                    ],
                ],
                actorUserId: 123,
            );

        $event = DomainAuditEvent::query()
            ->where(
                'action',
                'contract.created'
            )
            ->firstOrFail();

        $this->assertSame(
            'finance',
            $event->module
        );

        $this->assertSame(
            '123',
            (string) $event->actor_user_id
        );

        $this->assertSame(
            (string) $contract->id,
            $event->entity_id
        );
    }

    public function test_audit_redacts_sensitive_metadata(): void
    {
        $event = app(
            \App\Modules\Shared\Services\DomainAudit::class
        )->record(
            module: 'finance',
            action: 'security.test',
            metadata: [
                'token' => 'segredo',
                'nested' => [
                    'password' => 'segredo-2',
                ],
                'visible' => 'ok',
            ],
        );

        $this->assertSame(
            '[REDACTED]',
            $event->metadata['token']
        );

        $this->assertSame(
            '[REDACTED]',
            $event->metadata['nested']['password']
        );

        $this->assertSame(
            'ok',
            $event->metadata['visible']
        );
    }
}
