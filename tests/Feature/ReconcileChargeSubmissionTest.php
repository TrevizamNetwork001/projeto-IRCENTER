<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Modules\Finance\Actions\ActivateBillingContract;
use App\Modules\Finance\Actions\CreateBillingContract;
use App\Modules\Finance\Actions\GenerateInvoiceForContract;
use App\Modules\Finance\Actions\ReconcileChargeSubmission;
use App\Modules\Finance\Contracts\CorrelatablePaymentProvider;
use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Finance\Data\PaymentChargeRequest;
use App\Modules\Finance\Data\PaymentChargeResult;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Shared\Services\DomainAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReconcileChargeSubmissionTest extends TestCase
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

    public function test_submitting_charge_is_reconciled_without_new_post(): void
    {
        $invoice = $this->invoice();

        $charge = $this->charge(
            $invoice,
            Charge::STATUS_SUBMITTING,
        );

        $provider = $this->provider(
            new PaymentChargeResult(
                providerChargeId:
                    'probe-45000789',

                status:
                    Charge::STATUS_OPEN,

                checkoutUrl:
                    'https://boleto.test/45000789',

                pixCopyPaste:
                    '000201TESTE45000789',
            )
        );

        $action = new ReconcileChargeSubmission(
            $provider,
            app(DomainAudit::class),
        );

        $result = $action->handle(
            $charge->id
        );

        $this->assertSame(
            0,
            $provider->transactionLevel
        );

        $this->assertSame(
            1,
            $provider->correlationCalls
        );

        $this->assertSame(
            0,
            $provider->createCalls
        );

        $this->assertSame(
            Charge::STATUS_OPEN,
            $result->status
        );

        $this->assertSame(
            'probe-45000789',
            $result->provider_charge_id
        );

        $this->assertSame(
            'https://boleto.test/45000789',
            $result->provider_checkout_url
        );

        $this->assertSame(
            '000201TESTE45000789',
            $result->provider_pix_copy_paste
        );
    }

    public function test_unknown_charge_stays_unknown_when_not_found_and_is_not_retried(): void
    {
        $invoice = $this->invoice();

        $charge = $this->charge(
            $invoice,
            Charge::STATUS_SUBMISSION_UNKNOWN,
        );

        $provider = $this->provider(
            null
        );

        $action = new ReconcileChargeSubmission(
            $provider,
            app(DomainAudit::class),
        );

        $result = $action->handle(
            $charge->id
        );

        $this->assertSame(
            0,
            $provider->transactionLevel
        );

        $this->assertSame(
            1,
            $provider->correlationCalls
        );

        $this->assertSame(
            0,
            $provider->createCalls
        );

        $this->assertSame(
            Charge::STATUS_SUBMISSION_UNKNOWN,
            $result->status
        );

        $this->assertNull(
            $result->provider_charge_id
        );

        $this->assertNotNull(
            $result->last_synced_at
        );
    }

    private function provider(
        ?PaymentChargeResult $lookupResult,
    ): PaymentProvider&CorrelatablePaymentProvider {
        return new class(
            $lookupResult
        ) implements
            PaymentProvider,
            CorrelatablePaymentProvider
        {
            public int $createCalls = 0;

            public int $correlationCalls = 0;

            public int $transactionLevel = -1;

            public function __construct(
                private readonly
                    ?PaymentChargeResult $lookupResult,
            ) {
            }

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
                $this->createCalls++;

                throw new \RuntimeException(
                    'createCharge nao pode ser chamado '
                    .'na reconciliacao.'
                );
            }

            public function findCharge(
                string $providerChargeId,
            ): ?PaymentChargeResult {
                return null;
            }

            public function findChargeByCorrelation(
                string $correlationId,
                string $beginDate,
                string $endDate,
            ): ?PaymentChargeResult {
                $this->correlationCalls++;

                $this->transactionLevel =
                    DB::connection(
                        'finance_fiscal'
                    )->transactionLevel();

                return $this->lookupResult;
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
    }

    private function charge(
        Invoice $invoice,
        string $status,
    ): Charge {
        return Charge::query()->create([
            'invoice_id' =>
                $invoice->id,

            'provider' =>
                'probe',

            'method' =>
                Charge::METHOD_BOLETO,

            'status' =>
                $status,

            'idempotency_key' =>
                'invoice:'
                .$invoice->public_id
                .':provider:probe:method:boleto',

            'provider_charge_id' =>
                null,

            'amount' =>
                $invoice->total,

            'currency' =>
                $invoice->currency,

            'due_on' =>
                $invoice->due_on
                    ->toDateString(),
        ]);
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
