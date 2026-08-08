<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Modules\Finance\Actions\ActivateBillingContract;
use App\Modules\Finance\Actions\CreateBillingContract;
use App\Modules\Finance\Actions\CreateChargeForInvoice;
use App\Modules\Finance\Actions\GenerateInvoiceForContract;
use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Finance\Data\PaymentChargeRequest;
use App\Modules\Finance\Data\PaymentChargeResult;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Infrastructure\EfiPaymentProvider;
use App\Modules\Shared\Services\DomainAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ChargeSubmissionSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--database' =>
                'finance_fiscal',

            '--path' =>
                'database/migrations/finance_fiscal',

            '--force' => true,
        ]);

        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        config()->set(
            'finance_fiscal.finance.'
            .'payment_live_enabled',
            false
        );
    }

    public function test_provider_call_occurs_outside_finance_transaction(): void
    {
        $invoice = $this->invoice();

        $provider =
            new class implements PaymentProvider
            {
                public int $transactionLevel = -1;

                public int $calls = 0;

                public function key(): string
                {
                    return 'probe';
                }

                public function capabilities(): array
                {
                    return [
                        Charge::METHOD_BOLETO,
                    ];
                }

                public function isLive(): bool
                {
                    return false;
                }

                public function createCharge(
                    PaymentChargeRequest $request,
                ): PaymentChargeResult {
                    $this->calls++;

                    $this->transactionLevel =
                        DB::connection(
                            'finance_fiscal'
                        )->transactionLevel();

                    return new PaymentChargeResult(
                        providerChargeId:
                            'probe-1',

                        status:
                            Charge::STATUS_OPEN,
                    );
                }

                public function findCharge(
                    string $providerChargeId,
                ): ?PaymentChargeResult {
                    return null;
                }

                public function cancelCharge(
                    string $providerChargeId,
                    string $idempotencyKey,
                ): PaymentChargeResult {
                    return new PaymentChargeResult(
                        providerChargeId:
                            $providerChargeId,

                        status:
                            Charge::STATUS_CANCELED,
                    );
                }

                public function validateWebhookRequest(
                    string $rawBody,
                    array $headers,
                ): bool {
                    return true;
                }

                public function parseWebhook(
                    string $rawBody,
                    array $headers,
                ): array {
                    return [];
                }
            };

        $action = new CreateChargeForInvoice(
            $provider,
            app(DomainAudit::class),
        );

        $charge = $action->handle(
            $invoice->id,
            Charge::METHOD_BOLETO,
        );

        $this->assertSame(
            0,
            $provider->transactionLevel
        );

        $this->assertSame(
            1,
            $provider->calls
        );

        $this->assertSame(
            Charge::STATUS_OPEN,
            $charge->status
        );

        $this->assertSame(
            'probe-1',
            $charge->provider_charge_id
        );
    }

    public function test_uncertain_submission_is_never_automatically_retried(): void
    {
        $invoice = $this->invoice();

        $provider =
            new class implements PaymentProvider
            {
                public int $calls = 0;

                public function key(): string
                {
                    return 'probe';
                }

                public function capabilities(): array
                {
                    return [
                        Charge::METHOD_BOLETO,
                    ];
                }

                public function isLive(): bool
                {
                    return false;
                }

                public function createCharge(
                    PaymentChargeRequest $request,
                ): PaymentChargeResult {
                    $this->calls++;

                    throw new RuntimeException(
                        'simulated network failure'
                    );
                }

                public function findCharge(
                    string $providerChargeId,
                ): ?PaymentChargeResult {
                    return null;
                }

                public function cancelCharge(
                    string $providerChargeId,
                    string $idempotencyKey,
                ): PaymentChargeResult {
                    return new PaymentChargeResult(
                        providerChargeId:
                            $providerChargeId,

                        status:
                            Charge::STATUS_CANCELED,
                    );
                }

                public function validateWebhookRequest(
                    string $rawBody,
                    array $headers,
                ): bool {
                    return true;
                }

                public function parseWebhook(
                    string $rawBody,
                    array $headers,
                ): array {
                    return [];
                }
            };

        $action = new CreateChargeForInvoice(
            $provider,
            app(DomainAudit::class),
        );

        try {
            $action->handle(
                $invoice->id,
                Charge::METHOD_BOLETO,
            );

            $this->fail(
                'A falha simulada deveria propagar.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'simulated network failure',
                $exception->getMessage()
            );
        }

        $charge = Charge::query()
            ->where(
                'invoice_id',
                $invoice->id
            )
            ->firstOrFail();

        $this->assertSame(
            Charge::STATUS_SUBMISSION_UNKNOWN,
            $charge->status
        );

        $this->assertNull(
            $charge->provider_charge_id
        );

        $this->assertSame(
            1,
            $provider->calls
        );

        /*
         * Segunda chamada deve somente devolver
         * a reserva anterior. Nunca faz novo POST.
         */
        $sameCharge = $action->handle(
            $invoice->id,
            Charge::METHOD_BOLETO,
        );

        $this->assertSame(
            $charge->id,
            $sameCharge->id
        );

        $this->assertSame(
            Charge::STATUS_SUBMISSION_UNKNOWN,
            $sameCharge->status
        );

        $this->assertSame(
            1,
            $provider->calls
        );
    }

    public function test_efi_preflight_failure_creates_no_charge_or_http_request(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        config()->set('finance_fiscal.providers.efi.environment', 'homologation');
        config()->set('finance_fiscal.providers.efi.notification_url', null);

        $invoice = $this->invoice();
        $invoice->update(['client_phone_snapshot' => '+1 202 555 01234']);

        $action = new CreateChargeForInvoice(
            app(EfiPaymentProvider::class),
            app(DomainAudit::class),
        );

        try {
            $action->handle($invoice->id, Charge::METHOD_BOLETO);
            $this->fail('O preflight inválido deveria falhar.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(
                'Telefone do pagador é inválido.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('charges', 0, 'finance_fiscal');
        Http::assertNothingSent();
    }

    public function test_domain_blocks_different_method_when_charge_is_operational(): void
    {
        $invoice = $this->invoice();
        $provider = app(PaymentProvider::class);
        $action = new CreateChargeForInvoice($provider, app(DomainAudit::class));

        $first = $action->handle($invoice->id, Charge::METHOD_BOLETO);
        $second = $action->handle($invoice->id, Charge::METHOD_BOLETO_PIX);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('charges', 1, 'finance_fiscal');
    }

    private function invoice(): Invoice
    {
        $client =
            Client::factory()->create([
                'active' => true,
            ]);

        $contract = app(
            CreateBillingContract::class
        )->handle(
            clientId:
                $client->id,

            attributes: [],

            items: [
                [
                    'description' =>
                        'Servico teste',

                    'unit_amount' =>
                        '100.00',
                ],
            ],
        );

        $contract = app(
            ActivateBillingContract::class
        )->handle(
            $contract->id
        );

        return app(
            GenerateInvoiceForContract::class
        )->handle(
            $contract->id,
            '2026-08',
        );
    }
}
