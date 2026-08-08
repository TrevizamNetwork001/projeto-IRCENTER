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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('httpSubmissionFailures')]
    public function test_http_failure_preserves_unknown_with_sanitized_diagnostics(
        int $status,
        ?string $expectedCode,
    ): void {
        Http::preventStrayRequests();
        Log::spy();

        $secret = 'super-secret-response-value';

        Http::fake([
            'https://cobrancas-h.api.efipay.com.br/v1/authorize' =>
                Http::response(['access_token' => 'test-token']),
            'https://cobrancas-h.api.efipay.com.br/v1/charge/one-step' =>
                Http::response([
                    'code' => $expectedCode,
                    'message' => 'rejected '.$secret,
                    'authorization' => 'Bearer '.$secret,
                    'client_secret' => $secret,
                    'customer' => ['email' => $secret.'@example.test'],
                ], $status),
        ]);

        $invoice = $this->efiInvoice();

        try {
            $this->efiAction()->handle(
                $invoice->id,
                Charge::METHOD_BOLETO_PIX,
            );
            $this->fail('RequestException esperada.');
        } catch (RequestException) {
            // A resposta bruta nunca é transformada em mensagem da aplicação.
        }

        $charge = Charge::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame(Charge::STATUS_SUBMISSION_UNKNOWN, $charge->status);
        $this->assertNull($charge->provider_charge_id);

        $event = \App\Modules\Shared\Models\DomainAuditEvent::query()
            ->where('action', 'charge.submission_unknown')
            ->where('entity_id', (string) $charge->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($status, $event->metadata['http_status']);
        $this->assertSame(RequestException::class, $event->metadata['error_type']);
        $this->assertSame($expectedCode, $event->metadata['remote_error_code']);
        $this->assertStringNotContainsString($secret, json_encode($event->metadata));

        Log::shouldHaveReceived('warning')->once()->withArgs(
            function (string $message, array $context) use ($status, $secret): bool {
                $encoded = json_encode([$message, $context]);

                return $status === $context['http_status']
                    && RequestException::class === $context['error_type']
                    && ! str_contains($encoded, $secret)
                    && ! array_key_exists('message', $context)
                    && ! array_key_exists('response', $context);
            }
        );
    }

    public static function httpSubmissionFailures(): array
    {
        return [
            '422 explicit response' => [422, 'VALIDATION_ERROR'],
            '500 response' => [500, null],
        ];
    }

    public function test_connection_failure_preserves_unknown_without_http_response(): void
    {
        Http::preventStrayRequests();
        Log::spy();

        Http::fake([
            'https://cobrancas-h.api.efipay.com.br/v1/authorize' =>
                Http::response(['access_token' => 'test-token']),
            'https://cobrancas-h.api.efipay.com.br/v1/charge/one-step' =>
                Http::failedConnection('connection-secret-must-not-leak'),
        ]);

        $invoice = $this->efiInvoice();

        try {
            $this->efiAction()->handle(
                $invoice->id,
                Charge::METHOD_BOLETO_PIX,
            );
            $this->fail('ConnectionException esperada.');
        } catch (ConnectionException) {
            // Sem response: a submissão continua deliberadamente incerta.
        }

        $charge = Charge::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame(Charge::STATUS_SUBMISSION_UNKNOWN, $charge->status);

        $event = \App\Modules\Shared\Models\DomainAuditEvent::query()
            ->where('action', 'charge.submission_unknown')
            ->where('entity_id', (string) $charge->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(ConnectionException::class, $event->metadata['error_type']);
        $this->assertNull($event->metadata['http_status']);
        $this->assertStringNotContainsString(
            'connection-secret-must-not-leak',
            json_encode($event->metadata),
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

    public function test_any_risk_charge_blocks_same_and_different_method_in_domain(): void
    {
        $provider = new class implements PaymentProvider
        {
            public int $calls = 0;

            public function key(): string { return 'probe'; }
            public function capabilities(): array { return ['boleto', 'boleto_pix']; }
            public function isLive(): bool { return false; }
            public function createCharge(PaymentChargeRequest $request): PaymentChargeResult
            {
                $this->calls++;

                return new PaymentChargeResult('new-'.$this->calls, Charge::STATUS_OPEN);
            }
            public function findCharge(string $providerChargeId): ?PaymentChargeResult { return null; }
            public function cancelCharge(string $providerChargeId, string $idempotencyKey): PaymentChargeResult
            {
                return new PaymentChargeResult($providerChargeId, Charge::STATUS_CANCELED);
            }
            public function validateWebhookRequest(string $rawBody, array $headers): bool { return true; }
            public function parseWebhook(string $rawBody, array $headers): array { return []; }
        };

        $action = new CreateChargeForInvoice($provider, app(DomainAudit::class));

        foreach (Charge::blockingStatuses() as $status) {
            $invoice = $this->invoice();
            $existing = $this->charge($invoice, $status, Charge::METHOD_BOLETO);

            $same = $action->handle($invoice->id, Charge::METHOD_BOLETO);
            $different = $action->handle($invoice->id, Charge::METHOD_BOLETO_PIX);

            $this->assertSame($existing->id, $same->id, $status.' mesmo método');
            $this->assertSame($existing->id, $different->id, $status.' método diferente');
        }

        $this->assertSame(0, $provider->calls);
    }

    public function test_canceled_is_non_blocking_only_for_a_new_idempotency_key(): void
    {
        $provider = new class implements PaymentProvider
        {
            public int $calls = 0;
            public function key(): string { return 'probe'; }
            public function capabilities(): array { return ['boleto', 'boleto_pix']; }
            public function isLive(): bool { return false; }
            public function createCharge(PaymentChargeRequest $request): PaymentChargeResult
            {
                $this->calls++;
                return new PaymentChargeResult('replacement-1', Charge::STATUS_OPEN);
            }
            public function findCharge(string $providerChargeId): ?PaymentChargeResult { return null; }
            public function cancelCharge(string $providerChargeId, string $idempotencyKey): PaymentChargeResult
            {
                return new PaymentChargeResult($providerChargeId, Charge::STATUS_CANCELED);
            }
            public function validateWebhookRequest(string $rawBody, array $headers): bool { return true; }
            public function parseWebhook(string $rawBody, array $headers): array { return []; }
        };

        $invoice = $this->invoice();
        $canceled = $this->charge($invoice, Charge::STATUS_CANCELED, Charge::METHOD_BOLETO);
        $action = new CreateChargeForInvoice($provider, app(DomainAudit::class));

        $same = $action->handle($invoice->id, Charge::METHOD_BOLETO);
        $replacement = $action->handle($invoice->id, Charge::METHOD_BOLETO_PIX);

        $this->assertSame($canceled->id, $same->id);
        $this->assertNotSame($canceled->id, $replacement->id);
        $this->assertSame(1, $provider->calls);
    }

    #[DataProvider('riskyEfiStatuses')]
    public function test_efi_risky_remote_status_persists_failed_and_blocks_second_post(
        string $remoteStatus,
    ): void {
        Http::preventStrayRequests();
        Http::fake([
            'https://cobrancas-h.api.efipay.com.br/v1/authorize' =>
                Http::response(['access_token' => 'test-token']),
            'https://cobrancas-h.api.efipay.com.br/v1/charge/one-step' =>
                Http::response(['data' => ['charge_id' => 9001, 'status' => $remoteStatus]]),
        ]);

        config()->set('finance_fiscal.providers.efi.environment', 'homologation');
        config()->set('finance_fiscal.providers.efi.client_id', 'test-client');
        config()->set('finance_fiscal.providers.efi.client_secret', 'test-secret');
        config()->set('finance_fiscal.providers.efi.notification_url', null);

        $invoice = $this->invoice();
        $invoice->update([
            'client_legal_name_snapshot' => 'Cliente Efí Teste LTDA',
            'client_document_snapshot' => '12.345.678/0001-99',
            'billing_email_snapshot' => 'financeiro@cliente.test',
            'client_phone_snapshot' => '11986065675',
            'client_postal_code_snapshot' => '01001-000',
            'client_street_snapshot' => 'Praça da Sé',
            'client_address_number_snapshot' => '100',
            'client_district_snapshot' => 'Sé',
            'client_city_snapshot' => 'São Paulo',
            'client_state_snapshot' => 'SP',
            'client_country_snapshot' => 'BR',
        ]);
        $action = new CreateChargeForInvoice(
            app(EfiPaymentProvider::class),
            app(DomainAudit::class),
        );

        $first = $action->handle($invoice->id, Charge::METHOD_BOLETO);
        $second = $action->handle($invoice->id, Charge::METHOD_BOLETO_PIX);

        $this->assertSame(Charge::STATUS_FAILED, $first->status);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('charges', 1, 'finance_fiscal');
        Http::assertSentCount(2);
    }

    public static function riskyEfiStatuses(): array
    {
        return [
            'refunded' => ['refunded'],
            'contested' => ['contested'],
            'future unknown' => ['future_new_efi_status'],
        ];
    }

    private function efiAction(): CreateChargeForInvoice
    {
        config()->set('finance_fiscal.providers.efi.environment', 'homologation');
        config()->set('finance_fiscal.providers.efi.client_id', 'test-client');
        config()->set('finance_fiscal.providers.efi.client_secret', 'test-secret');
        config()->set('finance_fiscal.providers.efi.notification_url', null);

        return new CreateChargeForInvoice(
            app(EfiPaymentProvider::class),
            app(DomainAudit::class),
        );
    }

    private function efiInvoice(): Invoice
    {
        $invoice = $this->invoice();
        $invoice->update([
            'client_legal_name_snapshot' => 'Cliente Efí Teste LTDA',
            'client_document_snapshot' => '12.345.678/0001-99',
            'billing_email_snapshot' => 'financeiro@cliente.test',
            'client_phone_snapshot' => '11986065675',
            'client_postal_code_snapshot' => '01001-000',
            'client_street_snapshot' => 'Praça da Sé',
            'client_address_number_snapshot' => '100',
            'client_district_snapshot' => 'Sé',
            'client_city_snapshot' => 'São Paulo',
            'client_state_snapshot' => 'SP',
            'client_country_snapshot' => 'BR',
        ]);

        return $invoice->fresh();
    }

    private function charge(Invoice $invoice, string $status, string $method): Charge
    {
        return Charge::query()->create([
            'invoice_id' => $invoice->id,
            'provider' => 'probe',
            'method' => $method,
            'status' => $status,
            'idempotency_key' => sprintf(
                'invoice:%s:provider:probe:method:%s',
                $invoice->public_id,
                $method,
            ),
            'provider_charge_id' => 'existing-'.$invoice->id,
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'due_on' => $invoice->due_on->toDateString(),
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
