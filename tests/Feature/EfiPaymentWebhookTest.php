<?php

namespace Tests\Feature;

use App\Jobs\ProcessEfiPaymentWebhook;
use App\Models\Client;
use App\Modules\Finance\Actions\ActivateBillingContract;
use App\Modules\Finance\Actions\CreateBillingContract;
use App\Modules\Finance\Actions\CreateChargeForInvoice;
use App\Modules\Finance\Actions\GenerateInvoiceForContract;
use App\Modules\Finance\Infrastructure\EfiPaymentProvider;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\PaymentProviderEvent;
use App\Modules\Finance\Models\PaymentWebhookReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EfiPaymentWebhookTest extends TestCase
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
            'finance_fiscal.providers.'
            .'efi.environment',
            'homologation'
        );

        config()->set(
            'finance_fiscal.providers.'
            .'efi.client_id',
            'sandbox-client'
        );

        config()->set(
            'finance_fiscal.providers.'
            .'efi.client_secret',
            'sandbox-secret'
        );

        config()->set(
            'finance_fiscal.finance.'
            .'payment_live_enabled',
            false
        );
    }

    public function test_webhook_is_hidden_when_disabled(): void
    {
        config()->set(
            'finance_fiscal.finance.'
            .'payment_webhooks_enabled',
            false
        );

        $this->post(
            '/api/v1/webhooks/payments/efi',
            [
                'notification' =>
                    '09027955-5e06-4ff0-a9c7-46b47b8f1b27',
            ]
        )->assertNotFound();
    }

    public function test_webhook_encrypts_token_and_queues_job(): void
    {
        Queue::fake();

        config()->set(
            'finance_fiscal.finance.'
            .'payment_webhooks_enabled',
            true
        );

        $token =
            '09027955-5e06-4ff0-a9c7-46b47b8f1b27';

        $this->post(
            '/api/v1/webhooks/payments/efi',
            [
                'notification' => $token,
            ]
        )
            ->assertOk()
            ->assertJson([
                'accepted' => true,
            ]);

        $receipt =
            PaymentWebhookReceipt::query()
                ->firstOrFail();

        $this->assertSame(
            hash('sha256', $token),
            $receipt->token_hash
        );

        $this->assertNotSame(
            $token,
            $receipt->token_encrypted
        );

        $this->assertSame(
            $token,
            Crypt::decryptString(
                $receipt->token_encrypted
            )
        );

        Queue::assertPushed(
            ProcessEfiPaymentWebhook::class
        );
    }

    public function test_invalid_webhook_is_rejected(): void
    {
        Queue::fake();

        config()->set(
            'finance_fiscal.finance.'
            .'payment_webhooks_enabled',
            true
        );

        $this->post(
            '/api/v1/webhooks/payments/efi',
            [
                'notification' => 'x',
            ]
        )->assertUnprocessable();

        $this->assertSame(
            0,
            PaymentWebhookReceipt::query()
                ->count()
        );
    }

    public function test_job_consumes_history_and_updates_charge(): void
    {
        $charge = $this->efiCharge();

        $receipt =
            $this->receipt();

        Http::preventStrayRequests();

        Http::fake([
            'https://cobrancas-h.api.efipay.com.br/v1/authorize'
                => Http::response([
                    'access_token' =>
                        'token-test',
                ]),

            'https://cobrancas-h.api.efipay.com.br/v1/notification/*'
                => Http::response([
                    'code' => 200,
                    'data' => [
                        [
                            'id' => 1,
                            'type' => 'charge',
                            'identifiers' => [
                                'charge_id' =>
                                    900001,
                            ],
                            'status' => [
                                'current' =>
                                    'new',
                                'previous' =>
                                    null,
                            ],
                            'created_at' =>
                                '2026-08-07 18:00:00',
                        ],
                        [
                            'id' => 2,
                            'type' => 'charge',
                            'identifiers' => [
                                'charge_id' =>
                                    900001,
                            ],
                            'status' => [
                                'current' =>
                                    'waiting',
                                'previous' =>
                                    'new',
                            ],
                            'created_at' =>
                                '2026-08-07 18:01:00',
                        ],
                        [
                            'id' => 3,
                            'type' => 'charge',
                            'identifiers' => [
                                'charge_id' =>
                                    900001,
                            ],
                            'status' => [
                                'current' =>
                                    'paid',
                                'previous' =>
                                    'waiting',
                            ],
                            'value' => 85000,
                            'received_by_bank_at' =>
                                '2026-08-07',
                            'created_at' =>
                                '2026-08-07 18:10:00',
                        ],
                    ],
                ]),
        ]);

        $job = new ProcessEfiPaymentWebhook(
            $receipt->id
        );

        $job->handle(
            app(EfiPaymentProvider::class),
            app(
                \App\Modules\Shared\Services\DomainAudit::class
            ),
        );

        $charge->refresh();
        $receipt->refresh();

        $this->assertSame(
            Charge::STATUS_PAID,
            $charge->status
        );

        $this->assertSame(
            '3',
            $charge->last_provider_event_id
        );

        $this->assertSame(
            3,
            PaymentProviderEvent::query()
                ->count()
        );

        $paid =
            PaymentProviderEvent::query()
                ->where(
                    'provider_event_id',
                    '3'
                )
                ->firstOrFail();

        $this->assertSame(
            85000,
            $paid->value_cents
        );

        $this->assertSame(
            PaymentWebhookReceipt::
                STATUS_PROCESSED,
            $receipt->status
        );
    }

    public function test_replay_does_not_duplicate_events(): void
    {
        $charge = $this->efiCharge();

        Http::preventStrayRequests();

        Http::fake([
            'https://cobrancas-h.api.efipay.com.br/v1/authorize'
                => Http::response([
                    'access_token' =>
                        'token-test',
                ]),

            'https://cobrancas-h.api.efipay.com.br/v1/notification/*'
                => Http::response([
                    'code' => 200,
                    'data' => [
                        [
                            'id' => 1,
                            'type' => 'charge',
                            'identifiers' => [
                                'charge_id' =>
                                    900001,
                            ],
                            'status' => [
                                'current' =>
                                    'waiting',
                                'previous' =>
                                    'new',
                            ],
                        ],
                    ],
                ]),
        ]);

        foreach ([1, 2] as $_) {
            $receipt = $this->receipt();

            (new ProcessEfiPaymentWebhook(
                $receipt->id
            ))->handle(
                app(
                    EfiPaymentProvider::class
                ),
                app(
                    \App\Modules\Shared\Services\DomainAudit::class
                ),
            );
        }

        $this->assertSame(
            1,
            PaymentProviderEvent::query()
                ->count()
        );

        $charge->refresh();

        $this->assertSame(
            '1',
            $charge->last_provider_event_id
        );
    }

    private function receipt(): PaymentWebhookReceipt
    {
        $token =
            '09027955-5e06-4ff0-a9c7-46b47b8f1b27';

        return PaymentWebhookReceipt::query()
            ->create([
                'provider' => 'efi',
                'token_hash' =>
                    hash('sha256', $token),
                'token_encrypted' =>
                    Crypt::encryptString(
                        $token
                    ),
                'status' =>
                    PaymentWebhookReceipt::
                        STATUS_RECEIVED,
                'received_at' => now(),
            ]);
    }

    private function efiCharge(): Charge
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        $client =
            Client::factory()->create([
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
                        'Serviço',
                    'unit_amount' =>
                        '850.00',
                ],
            ],
        );

        $contract = app(
            ActivateBillingContract::class
        )->handle($contract->id);

        $invoice = app(
            GenerateInvoiceForContract::class
        )->handle(
            $contract->id,
            '2026-08'
        );

        $charge = app(
            CreateChargeForInvoice::class
        )->handle(
            $invoice->id,
            Charge::METHOD_PIX
        );

        $charge->update([
            'provider' => 'efi',
            'provider_charge_id' =>
                '900001',
        ]);

        return $charge->refresh();
    }
}
