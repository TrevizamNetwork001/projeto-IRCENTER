<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Modules\Finance\Actions\ActivateBillingContract;
use App\Modules\Finance\Actions\CreateBillingContract;
use App\Modules\Finance\Actions\CreateChargeForInvoice;
use App\Modules\Finance\Actions\GenerateInvoiceForContract;
use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Shared\Models\DomainAuditEvent;
use App\Modules\Shared\Services\DomainAudit;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use LogicException;
use Mockery;
use Tests\TestCase;

class ChargeDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--database' => 'finance_fiscal',
            '--path' =>
                'database/migrations/finance_fiscal',
            '--force' => true,
        ]);
    }

    public function test_charge_requires_finance_feature_flag(): void
    {
        $invoice = $this->invoice();

        $this->expectException(
            DomainException::class
        );

        app(CreateChargeForInvoice::class)
            ->handle(
                $invoice->id,
                Charge::METHOD_PIX
            );
    }

    public function test_fake_provider_creates_charge_without_live_mode(): void
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        $invoice = $this->invoice();

        $charge = app(
            CreateChargeForInvoice::class
        )->handle(
            $invoice->id,
            Charge::METHOD_BOLETO_PIX
        );

        $this->assertSame(
            'fake',
            $charge->provider
        );

        $this->assertSame(
            Charge::STATUS_OPEN,
            $charge->status
        );

        $this->assertSame(
            '850.00',
            $charge->amount
        );

        $this->assertStringStartsWith(
            'fake_',
            $charge->provider_charge_id
        );
    }

    public function test_charge_creation_is_idempotent(): void
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        $invoice = $this->invoice();

        $action = app(
            CreateChargeForInvoice::class
        );

        $first = $action->handle(
            $invoice->id,
            Charge::METHOD_PIX
        );

        $second = $action->handle(
            $invoice->id,
            Charge::METHOD_PIX
        );

        $this->assertSame(
            $first->id,
            $second->id
        );

        $this->assertSame(
            1,
            Charge::query()->count()
        );

        $this->assertSame(
            1,
            DomainAuditEvent::query()
                ->where(
                    'action',
                    'charge.created'
                )
                ->count()
        );
    }

    public function test_live_provider_is_blocked_when_live_flag_is_off(): void
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        config()->set(
            'finance_fiscal.finance.'
            .'payment_live_enabled',
            false
        );

        $provider = Mockery::mock(
            PaymentProvider::class
        );

        $provider->shouldReceive('key')
            ->andReturn('live-test');

        $provider->shouldReceive('isLive')
            ->andReturn(true);

        $provider->shouldReceive('capabilities')
            ->andReturn(['pix']);

        $provider->shouldReceive('createCharge')
            ->never();

        app()->instance(
            PaymentProvider::class,
            $provider
        );

        $invoice = $this->invoice();

        $this->expectException(
            DomainException::class
        );

        app(CreateChargeForInvoice::class)
            ->handle(
                $invoice->id,
                Charge::METHOD_PIX
            );
    }

    public function test_canceled_invoice_cannot_generate_charge(): void
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        $invoice = $this->invoice();

        $invoice->status =
            Invoice::STATUS_CANCELED;

        $invoice->save();

        $this->expectException(
            DomainException::class
        );

        app(CreateChargeForInvoice::class)
            ->handle(
                $invoice->id,
                Charge::METHOD_PIX
            );
    }

    public function test_charge_creation_is_audited(): void
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        $invoice = $this->invoice();

        $charge = app(
            CreateChargeForInvoice::class
        )->handle(
            $invoice->id,
            Charge::METHOD_BOLETO,
            321
        );

        $event = DomainAuditEvent::query()
            ->where(
                'action',
                'charge.created'
            )
            ->firstOrFail();

        $this->assertSame(
            (string) $charge->id,
            $event->entity_id
        );

        $this->assertSame(
            '321',
            (string) $event->actor_user_id
        );
    }

    public function test_domain_audit_event_cannot_be_updated(): void
    {
        $event = app(DomainAudit::class)
            ->record(
                module: 'finance',
                action: 'immutable.test',
            );

        $this->expectException(
            LogicException::class
        );

        $event->update([
            'action' => 'changed',
        ]);
    }

    public function test_domain_audit_event_cannot_be_deleted(): void
    {
        $event = app(DomainAudit::class)
            ->record(
                module: 'finance',
                action: 'immutable.test',
            );

        $this->expectException(
            LogicException::class
        );

        $event->delete();
    }

    private function invoice(): Invoice
    {
        $client = Client::factory()->create([
            'active' => true,
        ]);

        $contract = app(
            CreateBillingContract::class
        )->handle(
            clientId: $client->id,
            attributes: [],
            items: [
                [
                    'description' =>
                        'Gerenciamento de rede',
                    'unit_amount' =>
                        '850.00',
                ],
            ],
        );

        $contract = app(
            ActivateBillingContract::class
        )->handle($contract->id);

        return app(
            GenerateInvoiceForContract::class
        )->handle(
            $contract->id,
            '2026-08'
        );
    }
}
