<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Modules\Finance\Actions\ActivateBillingContract;
use App\Modules\Finance\Actions\CreateBillingContract;
use App\Modules\Finance\Actions\GenerateInvoiceForContract;
use App\Modules\Finance\Models\BillingContract;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Shared\Models\DomainAuditEvent;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Tests\TestCase;

class InvoiceDomainTest extends TestCase
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

    public function test_contract_activation_is_explicit_and_audited(): void
    {
        [, $contract] = $this->createContract();

        $this->assertSame(
            BillingContract::STATUS_DRAFT,
            $contract->status
        );

        $contract = app(
            ActivateBillingContract::class
        )->handle($contract->id, 123);

        $this->assertSame(
            BillingContract::STATUS_ACTIVE,
            $contract->status
        );

        $this->assertSame(
            1,
            DomainAuditEvent::query()
                ->where(
                    'action',
                    'contract.activated'
                )
                ->count()
        );
    }

    public function test_recurring_invoice_snapshots_contract_and_amounts(): void
    {
        [$client, $contract] =
            $this->createActiveContract();

        $invoice = app(
            GenerateInvoiceForContract::class
        )->handle(
            $contract->id,
            '2026-08'
        );

        $this->assertSame(
            $client->id,
            $invoice->core_client_id
        );

        $this->assertSame(
            Invoice::SOURCE_RECURRING,
            $invoice->source
        );

        $this->assertSame(
            Invoice::STATUS_OPEN,
            $invoice->status
        );

        $this->assertSame(
            '2026-08-01',
            $invoice->competence_month
                ->toDateString()
        );

        $this->assertSame(
            '2026-08-20',
            $invoice->due_on->toDateString()
        );

        $this->assertSame(
            '850.00',
            $invoice->subtotal
        );

        $this->assertSame(
            '850.00',
            $invoice->total
        );

        $this->assertCount(1, $invoice->items);
    }

    public function test_same_competence_is_idempotent(): void
    {
        [, $contract] =
            $this->createActiveContract();

        $action = app(
            GenerateInvoiceForContract::class
        );

        $first = $action->handle(
            $contract->id,
            '2026-08'
        );

        $second = $action->handle(
            $contract->id,
            '2026-08'
        );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            1,
            Invoice::query()->count()
        );

        $this->assertSame(
            1,
            DomainAuditEvent::query()
                ->where(
                    'action',
                    'invoice.generated'
                )
                ->count()
        );
    }

    public function test_different_competences_create_different_invoices(): void
    {
        [, $contract] =
            $this->createActiveContract();

        $action = app(
            GenerateInvoiceForContract::class
        );

        $action->handle(
            $contract->id,
            '2026-08'
        );

        $action->handle(
            $contract->id,
            '2026-09'
        );

        $this->assertSame(
            2,
            Invoice::query()->count()
        );
    }

    public function test_due_day_is_clamped_to_last_day_of_month(): void
    {
        [, $contract] = $this->createActiveContract(
            ['due_day' => 31]
        );

        $invoice = app(
            GenerateInvoiceForContract::class
        )->handle(
            $contract->id,
            '2028-02'
        );

        $this->assertSame(
            '2028-02-29',
            $invoice->due_on->toDateString()
        );
    }

    public function test_draft_contract_cannot_generate_invoice(): void
    {
        [, $contract] = $this->createContract();

        $this->expectException(
            DomainException::class
        );

        app(
            GenerateInvoiceForContract::class
        )->handle(
            $contract->id,
            '2026-08'
        );
    }

    public function test_decimal_quantity_is_calculated_without_float(): void
    {
        [, $contract] = $this->createActiveContract(
            items: [
                [
                    'description' => 'Serviço fracionado',
                    'quantity' => '1.5000',
                    'unit_amount' => '100.00',
                ],
            ]
        );

        $invoice = app(
            GenerateInvoiceForContract::class
        )->handle(
            $contract->id,
            '2026-08'
        );

        $this->assertSame(
            '150.00',
            $invoice->total
        );
    }

    public function test_contract_rejects_float_money_input(): void
    {
        $client = Client::factory()->create([
            'active' => true,
        ]);

        $this->expectException(
            InvalidArgumentException::class
        );

        app(CreateBillingContract::class)
            ->handle(
                clientId: $client->id,
                attributes: [],
                items: [
                    [
                        'description' => 'Serviço',
                        'unit_amount' => 100.25,
                    ],
                ],
            );
    }

    public function test_invoice_freezes_current_payer_snapshot(): void
    {
        $client = Client::factory()->create([
            'legal_name' =>
                'Empresa Snapshot LTDA',
            'document' =>
                '12345678000199',
            'phone' =>
                '1133334444',
            'postal_code' =>
                '01001000',
            'street' =>
                'Rua Antiga',
            'address_number' =>
                '10',
            'district' =>
                'Centro',
            'city' =>
                'São Paulo',
            'state' =>
                'SP',
            'country' =>
                'BR',
            'active' =>
                true,
        ]);

        $contract = app(
            CreateBillingContract::class
        )->handle(
            clientId: $client->id,
            attributes: [
                'billing_email_override' =>
                    'financeiro@cliente.test',
            ],
            items: [
                [
                    'description' => 'Serviço',
                    'unit_amount' => '100.00',
                ],
            ],
        );

        $client->update([
            'street' => 'Rua Atual',
            'address_number' => '99',
        ]);

        $contract = app(
            ActivateBillingContract::class
        )->handle($contract->id);

        $invoice = app(
            GenerateInvoiceForContract::class
        )->handle(
            $contract->id,
            '2026-08'
        );

        $this->assertSame(
            'Rua Atual',
            $invoice->client_street_snapshot
        );

        $this->assertSame(
            '99',
            $invoice
                ->client_address_number_snapshot
        );

        $this->assertSame(
            'financeiro@cliente.test',
            $invoice->billing_email_snapshot
        );

        $client->update([
            'street' => 'Rua Posterior',
        ]);

        $this->assertSame(
            'Rua Atual',
            $invoice->fresh()
                ->client_street_snapshot
        );
    }

    private function createActiveContract(
        array $attributes = [],
        array $items = [],
    ): array {
        [$client, $contract] =
            $this->createContract(
                $attributes,
                $items
            );

        $contract = app(
            ActivateBillingContract::class
        )->handle($contract->id);

        return [$client, $contract];
    }

    private function createContract(
        array $attributes = [],
        array $items = [],
    ): array {
        $client = Client::factory()->create([
            'legal_name' => 'Empresa XYZ LTDA',
            'trade_name' => 'Empresa XYZ',
            'active' => true,
        ]);

        if ($items === []) {
            $items = [
                [
                    'description' =>
                        'Gerenciamento de rede',
                    'quantity' => '1.0000',
                    'unit_amount' => '850.00',
                ],
            ];
        }

        $contract = app(
            CreateBillingContract::class
        )->handle(
            clientId: $client->id,
            attributes: array_merge(
                [
                    'generation_day' => 5,
                    'due_day' => 20,
                ],
                $attributes
            ),
            items: $items,
        );

        return [$client, $contract];
    }
}
