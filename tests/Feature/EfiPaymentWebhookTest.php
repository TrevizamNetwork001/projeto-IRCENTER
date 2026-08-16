<?php

namespace Tests\Feature;

use App\Jobs\ProcessEfiPaymentWebhook;
use App\Models\Client;
use App\Models\User;
use App\Modules\Finance\Actions\ActivateBillingContract;
use App\Modules\Finance\Actions\CreateBillingContract;
use App\Modules\Finance\Actions\CreateChargeForInvoice;
use App\Modules\Finance\Actions\GenerateInvoiceForContract;
use App\Modules\Finance\Actions\SyncChargeFromProvider;
use App\Modules\Finance\Infrastructure\EfiPaymentProvider;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentProviderEvent;
use App\Modules\Finance\Models\PaymentWebhookReceipt;
use App\Modules\Shared\Models\DomainAuditEvent;
use App\Modules\Shared\Services\DomainAudit;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class EfiPaymentWebhookTest extends TestCase
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

        config()->set(
            'finance_fiscal.providers.efi.webhook_callback_secret',
            str_repeat('a', 64)
        );

        config()->set(
            'finance_fiscal.providers.efi.webhook_legacy_route_enabled',
            true
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
                'notification' => '09027955-5e06-4ff0-a9c7-46b47b8f1b27',
            ]
        )->assertNotFound();
    }

    public function test_job_uses_deterministic_receipt_lock_without_secrets(): void
    {
        $first = new ProcessEfiPaymentWebhook(41);
        $same = new ProcessEfiPaymentWebhook(41);
        $different = new ProcessEfiPaymentWebhook(42);

        $middleware = $first->middleware()[0];

        $this->assertInstanceOf(WithoutOverlapping::class, $middleware);
        $this->assertSame(10, $middleware->releaseAfter);
        $this->assertSame(60, $middleware->expiresAfter);
        $this->assertSame(0, $first->tries);
        $this->assertSame(5, $first->maxExceptions);
        $this->assertSame(
            $middleware->getLockKey($first),
            $same->middleware()[0]->getLockKey($same)
        );
        $this->assertNotSame(
            $middleware->getLockKey($first),
            $different->middleware()[0]->getLockKey($different)
        );

        $key = $middleware->getLockKey($first);

        foreach ([
            'notification-token-test',
            str_repeat('a', 64),
            'sandbox-secret',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $key);
        }
    }

    public function test_occupied_lock_releases_job_without_financial_failure(): void
    {
        Http::preventStrayRequests();
        $receipt = $this->receipt();
        $job = new ProcessEfiPaymentWebhook($receipt->id);
        $middleware = $job->middleware()[0];
        $lock = Cache::lock($middleware->getLockKey($job), 60);
        $this->assertTrue($lock->get());

        $queuedJob = Mockery::mock(QueueJob::class);
        $queuedJob->shouldReceive('release')->once()->with(10);
        $job->setJob($queuedJob);
        $entered = false;

        try {
            $middleware->handle($job, function () use (&$entered): void {
                $entered = true;
            });
        } finally {
            $lock->release();
        }

        $this->assertFalse($entered);
        $this->assertSame(
            PaymentWebhookReceipt::STATUS_RECEIVED,
            $receipt->refresh()->status
        );
        $this->assertDatabaseMissing('domain_audit_events', [
            'action' => 'payment_webhook.token_not_found',
        ], 'finance_fiscal');
        $this->assertDatabaseCount('payments', 0, 'finance_fiscal');
        Http::assertNothingSent();
    }

    public function test_job_can_enter_after_receipt_lock_is_released(): void
    {
        $job = new ProcessEfiPaymentWebhook(51);
        $middleware = $job->middleware()[0];
        $lock = Cache::lock($middleware->getLockKey($job), 60);
        $this->assertTrue($lock->get());
        $lock->release();
        $entered = false;

        $middleware->handle($job, function () use (&$entered): void {
            $entered = true;
        });

        $this->assertTrue($entered);
    }

    public function test_missing_callback_configuration_fails_closed(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        config()->set('finance_fiscal.finance.payment_webhooks_enabled', true);
        config()->set('finance_fiscal.providers.efi.webhook_callback_secret', null);
        config()->set('finance_fiscal.providers.efi.webhook_legacy_route_enabled', false);

        $this->post($this->protectedWebhookUrl(), [
            'notification' => '19027955-5e06-4ff0-a9c7-46b47b8f1b27',
        ])->assertNotFound();

        $this->assertDatabaseCount('payment_webhook_receipts', 0, 'finance_fiscal');
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_missing_or_invalid_callback_is_rejected_before_work(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        config()->set('finance_fiscal.finance.payment_webhooks_enabled', true);
        config()->set('finance_fiscal.providers.efi.webhook_legacy_route_enabled', false);

        $payload = [
            'notification' => '29027955-5e06-4ff0-a9c7-46b47b8f1b27',
        ];

        $this->post('/api/v1/webhooks/payments/efi', $payload)
            ->assertNotFound();
        $response = $this->post(
            '/api/v1/webhooks/payments/efi/'.str_repeat('b', 64),
            $payload
        )->assertNotFound();

        $this->assertStringNotContainsString(
            str_repeat('a', 64),
            $response->getContent()
        );
        $this->assertDatabaseCount('payment_webhook_receipts', 0, 'finance_fiscal');
        $this->assertDatabaseCount('domain_audit_events', 0, 'finance_fiscal');
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_correct_callback_secret_allows_normal_flow_without_persisting_secret(): void
    {
        Queue::fake();
        config()->set('finance_fiscal.finance.payment_webhooks_enabled', true);
        config()->set('finance_fiscal.providers.efi.webhook_legacy_route_enabled', false);

        $response = $this->post($this->protectedWebhookUrl(), [
            'notification' => '39027955-5e06-4ff0-a9c7-46b47b8f1b27',
        ])->assertOk();

        $secret = str_repeat('a', 64);
        $this->assertStringNotContainsString($secret, $response->getContent());
        $this->assertStringNotContainsString(
            $secret,
            PaymentWebhookReceipt::query()->firstOrFail()->toJson()
        );
        $this->assertStringNotContainsString(
            $secret,
            DomainAuditEvent::query()->firstOrFail()->toJson()
        );
        Queue::assertPushed(ProcessEfiPaymentWebhook::class, 1);
    }

    public function test_invalid_callbacks_are_rate_limited_before_authentication(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        config()->set('finance_fiscal.finance.payment_webhooks_enabled', true);
        config()->set('finance_fiscal.providers.efi.webhook_legacy_route_enabled', false);
        config()->set('finance_fiscal.providers.efi.webhook_rate_limit', 2);
        $url = '/api/v1/webhooks/payments/efi/'.str_repeat('c', 64);
        $server = ['REMOTE_ADDR' => '198.51.100.77'];

        $this->withServerVariables($server)->post($url)->assertNotFound();
        $this->withServerVariables($server)->post($url)->assertNotFound();
        $response = $this->withServerVariables($server)->post($url)
            ->assertTooManyRequests();

        $this->assertTrue(
            $response->headers->has('Retry-After')
            || $response->headers->has('X-RateLimit-Reset')
        );
        $this->assertDatabaseCount('payment_webhook_receipts', 0, 'finance_fiscal');
        Queue::assertNothingPushed();
        Http::assertNothingSent();
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

    public function test_webhook_accepts_real_form_urlencoded_and_json(): void
    {
        Queue::fake();
        config()->set('finance_fiscal.finance.payment_webhooks_enabled', true);
        $token = '09027955-5e06-4ff0-a9c7-46b47b8f1b27';

        $this->call(
            'POST', '/api/v1/webhooks/payments/efi', [], [], [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            'notification='.rawurlencode($token),
        )->assertOk();

        $this->postJson('/api/v1/webhooks/payments/efi', [
            'notification' => $token,
        ])->assertOk()->assertJson(['duplicate_token' => true]);

        $this->assertDatabaseCount('payment_webhook_receipts', 1, 'finance_fiscal');
        $this->assertSame(2, PaymentWebhookReceipt::query()->firstOrFail()->receive_count);
        Queue::assertPushed(ProcessEfiPaymentWebhook::class, 2);
    }

    public function test_webhook_rejects_missing_empty_and_non_scalar_tokens(): void
    {
        config()->set('finance_fiscal.finance.payment_webhooks_enabled', true);

        foreach ([
            [],
            ['notification' => ''],
            ['notification' => []],
            ['notification' => ['token' => 'value']],
        ] as $payload) {
            $this->postJson('/api/v1/webhooks/payments/efi', $payload)
                ->assertUnprocessable();
        }

        $this->assertDatabaseCount('payment_webhook_receipts', 0, 'finance_fiscal');
    }

    public function test_job_consumes_history_and_updates_charge(): void
    {
        $charge = $this->efiCharge();

        $receipt =
            $this->receipt();

        Http::preventStrayRequests();

        Http::fake([
            'https://cobrancas-h.api.efipay.com.br/v1/authorize' => Http::response([
                'access_token' => 'token-test',
            ]),

            'https://cobrancas-h.api.efipay.com.br/v1/notification/*' => Http::response([
                'code' => 200,
                'data' => [
                    [
                        'id' => 1,
                        'type' => 'charge',
                        'identifiers' => [
                            'charge_id' => 900001,
                        ],
                        'status' => [
                            'current' => 'new',
                            'previous' => null,
                        ],
                        'created_at' => '2026-08-07 18:00:00',
                    ],
                    [
                        'id' => 2,
                        'type' => 'charge',
                        'identifiers' => [
                            'charge_id' => 900001,
                        ],
                        'status' => [
                            'current' => 'waiting',
                            'previous' => 'new',
                        ],
                        'created_at' => '2026-08-07 18:01:00',
                    ],
                    [
                        'id' => 3,
                        'type' => 'charge',
                        'identifiers' => [
                            'charge_id' => 900001,
                        ],
                        'status' => [
                            'current' => 'paid',
                            'previous' => 'waiting',
                        ],
                        'value' => 85000,
                        'received_by_bank_at' => '2026-08-07',
                        'created_at' => '2026-08-07 18:10:00',
                    ],
                ],
            ]),
        ]);

        $job = new ProcessEfiPaymentWebhook(
            $receipt->id
        );

        $job->handle(
            app(EfiPaymentProvider::class),
            app(SyncChargeFromProvider::class),
            app(DomainAudit::class),
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
            PaymentWebhookReceipt::STATUS_PROCESSED,
            $receipt->status
        );

        $this->assertDatabaseCount('payments', 1, 'finance_fiscal');

        $payment = Payment::query()->firstOrFail();

        $this->assertSame('850.00', $payment->amount);
        $this->assertSame('3', $payment->provider_payment_id);
        $this->assertSame('paid', $charge->invoice()->firstOrFail()->status);
    }

    public function test_replay_does_not_duplicate_events(): void
    {
        $charge = $this->efiCharge();

        Http::preventStrayRequests();

        Http::fake([
            'https://cobrancas-h.api.efipay.com.br/v1/authorize' => Http::response([
                'access_token' => 'token-test',
            ]),

            'https://cobrancas-h.api.efipay.com.br/v1/notification/*' => Http::response([
                'code' => 200,
                'data' => [
                    [
                        'id' => 1,
                        'type' => 'charge',
                        'identifiers' => [
                            'charge_id' => 900001,
                        ],
                        'status' => [
                            'current' => 'waiting',
                            'previous' => 'new',
                        ],
                    ],
                ],
            ]),
        ]);

        $receipt = $this->receipt();

        foreach ([1, 2] as $_) {

            (new ProcessEfiPaymentWebhook(
                $receipt->id
            ))->handle(
                app(
                    EfiPaymentProvider::class
                ),
                app(SyncChargeFromProvider::class),
                app(DomainAudit::class),
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

    public function test_same_webhook_replayed_ten_times_creates_one_receipt_and_ten_processing_opportunities(): void
    {
        Queue::fake();
        config()->set('finance_fiscal.finance.payment_webhooks_enabled', true);

        $payload = [
            'notification' => '09027955-5e06-4ff0-a9c7-46b47b8f1b27',
        ];

        foreach (range(1, 10) as $_) {
            $this->post('/api/v1/webhooks/payments/efi', $payload)->assertOk();
        }

        $this->assertDatabaseCount('payment_webhook_receipts', 1, 'finance_fiscal');
        $receipt = PaymentWebhookReceipt::query()->firstOrFail();
        $this->assertSame(10, $receipt->receive_count);
        $this->assertNotNull($receipt->last_received_at);
        Queue::assertPushed(ProcessEfiPaymentWebhook::class, 10);
    }

    public function test_same_token_waiting_then_paid_fetches_again_and_processes_only_new_event(): void
    {
        Queue::fake();
        config()->set('finance_fiscal.finance.payment_webhooks_enabled', true);
        $charge = $this->efiCharge();
        $token = '49027955-5e06-4ff0-a9c7-46b47b8f1b27';

        Http::preventStrayRequests();
        Http::fake([
            '*/v1/authorize' => Http::response(['access_token' => 'token-test']),
            '*/v1/notification/*' => Http::sequence()
                ->push(['code' => 200, 'data' => [$this->event(100, 'waiting')]])
                ->push(['code' => 200, 'data' => [
                    $this->event(100, 'waiting'),
                    $this->event(101, 'paid', 85000),
                ]]),
        ]);

        $this->post('/api/v1/webhooks/payments/efi', ['notification' => $token])
            ->assertOk()
            ->assertJson(['duplicate_token' => false, 'processing_scheduled' => true]);

        $receipt = PaymentWebhookReceipt::query()->firstOrFail();
        $this->processReceipt($receipt);
        $this->assertSame(Charge::STATUS_OPEN, $charge->refresh()->status);

        $this->post('/api/v1/webhooks/payments/efi', ['notification' => $token])
            ->assertOk()
            ->assertJson(['duplicate_token' => true, 'processing_scheduled' => true]);
        $this->processReceipt($receipt->refresh());

        $this->assertSame(2, $receipt->refresh()->receive_count);
        Queue::assertPushed(ProcessEfiPaymentWebhook::class, 2);
        $notificationGets = collect(Http::recorded())
            ->filter(fn (array $exchange): bool => str_contains(
                $exchange[0]->url(),
                '/v1/notification/'
            ));
        $this->assertCount(2, $notificationGets);
        $this->assertDatabaseCount('payment_provider_events', 2, 'finance_fiscal');
        $this->assertDatabaseCount('payments', 1, 'finance_fiscal');
        $this->assertSame(Charge::STATUS_PAID, $charge->refresh()->status);
        $this->assertSame(Invoice::STATUS_PAID, $charge->invoice()->firstOrFail()->status);
    }

    public function test_ten_same_token_fetches_with_unchanged_history_are_domain_idempotent(): void
    {
        Queue::fake();
        config()->set('finance_fiscal.finance.payment_webhooks_enabled', true);
        $charge = $this->efiCharge();
        $token = '59027955-5e06-4ff0-a9c7-46b47b8f1b27';

        Http::preventStrayRequests();
        Http::fake([
            '*/v1/authorize' => Http::response(['access_token' => 'token-test']),
            '*/v1/notification/*' => Http::response([
                'code' => 200,
                'data' => [$this->event(100, 'waiting')],
            ]),
        ]);

        foreach (range(1, 10) as $_) {
            $this->post('/api/v1/webhooks/payments/efi', ['notification' => $token])->assertOk();
            $this->processReceipt(PaymentWebhookReceipt::query()->firstOrFail());
        }

        $this->assertSame(10, PaymentWebhookReceipt::query()->firstOrFail()->receive_count);
        Queue::assertPushed(ProcessEfiPaymentWebhook::class, 10);
        $this->assertDatabaseCount('payment_provider_events', 1, 'finance_fiscal');
        $this->assertDatabaseCount('payments', 0, 'finance_fiscal');
        $this->assertSame(Charge::STATUS_OPEN, $charge->refresh()->status);
    }

    public function test_same_token_can_retry_after_notification_get_failure(): void
    {
        Queue::fake();
        config()->set('finance_fiscal.finance.payment_webhooks_enabled', true);
        $charge = $this->efiCharge();
        $token = '69027955-5e06-4ff0-a9c7-46b47b8f1b27';

        Http::preventStrayRequests();
        Http::fake([
            '*/v1/authorize' => Http::response(['access_token' => 'token-test']),
            '*/v1/notification/*' => Http::sequence()
                ->push(['error' => 'temporary'], 500)
                ->push(['code' => 200, 'data' => [
                    $this->event(100, 'waiting'),
                    $this->event(101, 'paid', 85000),
                ]]),
        ]);

        $this->post('/api/v1/webhooks/payments/efi', ['notification' => $token])->assertOk();
        $receipt = PaymentWebhookReceipt::query()->firstOrFail();

        try {
            $this->processReceipt($receipt);
            $this->fail('Falha temporária deveria ser propagada.');
        } catch (\Throwable) {
            // O callback seguinte com o mesmo token oferece novo processamento.
        }

        $this->assertSame(PaymentWebhookReceipt::STATUS_FAILED, $receipt->refresh()->status);
        $this->post('/api/v1/webhooks/payments/efi', ['notification' => $token])->assertOk();
        $this->processReceipt($receipt->refresh());

        $this->assertSame(2, $receipt->refresh()->receive_count);
        $this->assertSame(PaymentWebhookReceipt::STATUS_PROCESSED, $receipt->status);
        $this->assertSame(Charge::STATUS_PAID, $charge->refresh()->status);
        $this->assertDatabaseCount('payments', 1, 'finance_fiscal');
    }

    public function test_new_event_after_paid_is_discovered_with_same_token(): void
    {
        $charge = $this->efiCharge();
        $receipt = $this->receipt();

        $this->runEvents($receipt, [
            $this->event(100, 'waiting'),
            $this->event(101, 'paid', 85000),
            $this->event(102, 'contested', 85000),
        ]);

        $this->assertDatabaseCount('payment_provider_events', 3, 'finance_fiscal');
        $this->assertDatabaseCount('payments', 1, 'finance_fiscal');
        $this->assertSame(Charge::STATUS_FAILED, $charge->refresh()->status);
        $this->assertSame(Invoice::STATUS_PAID, $charge->invoice()->firstOrFail()->status);
    }

    public function test_paid_followed_by_newer_waiting_does_not_regress_or_duplicate_payment(): void
    {
        $charge = $this->efiCharge();
        $this->runEvents($this->receipt(), [
            $this->event(10, 'paid', 85000),
            $this->event(11, 'waiting'),
            $this->event(12, 'paid', 85000),
        ]);

        $this->assertSame(Charge::STATUS_PAID, $charge->refresh()->status);
        $this->assertDatabaseCount('payments', 1, 'finance_fiscal');
        $this->assertSame(Invoice::STATUS_PAID, $charge->invoice()->firstOrFail()->status);
    }

    public function test_paid_amount_mismatch_records_payment_but_does_not_pay_invoice(): void
    {
        $charge = $this->efiCharge();
        $this->runEvents($this->receipt(), [
            $this->event(20, 'paid', 84999),
        ]);

        $this->assertSame(Charge::STATUS_PAID, $charge->refresh()->status);
        $this->assertDatabaseCount('payments', 1, 'finance_fiscal');
        $this->assertSame(Invoice::STATUS_OPEN, $charge->invoice()->firstOrFail()->status);
        $this->assertDatabaseHas('domain_audit_events', [
            'action' => 'payment.amount_mismatch',
        ], 'finance_fiscal');
    }

    public function test_manual_settlement_uses_nominal_charge_amount_idempotently(): void
    {
        $charge = $this->efiCharge();
        $receipt = $this->receipt();
        $events = [$this->event(40, 'settled')];

        $this->runEvents($receipt, $events);
        $this->runEvents($receipt->refresh(), $events);

        $this->assertSame(Charge::STATUS_PAID, $charge->refresh()->status);
        $this->assertSame(Invoice::STATUS_PAID, $charge->invoice()->firstOrFail()->status);
        $this->assertDatabaseCount('payments', 1, 'finance_fiscal');
        $this->assertSame('850.00', Payment::query()->firstOrFail()->amount);
    }

    public function test_paid_invoice_web_shows_payment_and_financial_timeline(): void
    {
        $charge = $this->efiCharge();
        $this->runEvents($this->receipt(), [
            $this->event(25, 'paid', 85000),
        ]);

        $viewer = User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($viewer)
            ->get(route('finance.invoices.show', $charge->invoice_id))
            ->assertOk()
            ->assertSee('Pago')
            ->assertSee('Valor recebido')
            ->assertSee('850,00')
            ->assertSee('EFI')
            ->assertSee('Histórico')
            ->assertSee('Pagamento registrado')
            ->assertSee('Fatura paga')
            ->assertDontSee('Gerar cobrança');
    }

    public function test_expired_canceled_and_unknown_statuses_are_conservative(): void
    {
        $expired = $this->efiCharge();
        $canceled = $this->efiCharge();
        $canceled->update(['provider_charge_id' => '900002']);
        $unknown = $this->efiCharge();
        $unknown->update(['provider_charge_id' => '900003']);

        $this->runEvents($this->receipt(), [
            $this->event(30, 'expired'),
            $this->event(31, 'canceled', null, '900002'),
            $this->event(32, 'future_status', null, '900003'),
        ]);

        $this->assertSame(Charge::STATUS_OVERDUE, $expired->refresh()->status);
        $this->assertSame(Invoice::STATUS_OPEN, $expired->invoice()->firstOrFail()->status);
        $this->assertSame(Charge::STATUS_CANCELED, $canceled->refresh()->status);
        $this->assertSame(Charge::STATUS_FAILED, $unknown->refresh()->status);
        $this->assertDatabaseCount('payments', 0, 'finance_fiscal');
    }

    public function test_provider_failure_does_not_change_domain_and_marks_receipt_failed(): void
    {
        $charge = $this->efiCharge();
        $receipt = $this->receipt();

        Http::preventStrayRequests();
        Http::fake([
            '*/v1/authorize' => Http::response(['access_token' => 'token-test']),
            '*/v1/notification/*' => Http::response(['error' => 'provider failure'], 500),
        ]);

        try {
            (new ProcessEfiPaymentWebhook($receipt->id))->handle(
                app(EfiPaymentProvider::class),
                app(SyncChargeFromProvider::class),
                app(DomainAudit::class),
            );
            $this->fail('Falha do provider deveria ser propagada.');
        } catch (\Throwable) {
            // Esperado: a fila poderá tentar novamente.
        }

        $this->assertSame(Charge::STATUS_OPEN, $charge->refresh()->status);
        $this->assertSame(PaymentWebhookReceipt::STATUS_FAILED, $receipt->refresh()->status);
        $this->assertDatabaseCount('payments', 0, 'finance_fiscal');
    }

    public function test_provider_timeout_does_not_mark_charge_paid(): void
    {
        $charge = $this->efiCharge();
        $receipt = $this->receipt();

        Http::preventStrayRequests();
        Http::fake([
            '*/v1/authorize' => Http::response(['access_token' => 'token-test']),
            '*/v1/notification/*' => fn () => throw new ConnectionException('timeout'),
        ]);

        try {
            (new ProcessEfiPaymentWebhook($receipt->id))->handle(
                app(EfiPaymentProvider::class),
                app(SyncChargeFromProvider::class),
                app(DomainAudit::class),
            );
            $this->fail('Timeout deveria ser propagado.');
        } catch (ConnectionException) {
            // Esperado: sem alteração financeira.
        }

        $this->assertSame(Charge::STATUS_OPEN, $charge->refresh()->status);
        $this->assertSame(PaymentWebhookReceipt::STATUS_FAILED, $receipt->refresh()->status);
        $this->assertDatabaseCount('payments', 0, 'finance_fiscal');
    }

    public function test_notification_not_found_is_safely_classified_without_financial_effect(): void
    {
        $charge = $this->efiCharge();
        $token = '79027955-5e06-4ff0-a9c7-46b47b8f1b27';
        $receipt = $this->receipt($token);

        Http::preventStrayRequests();
        Http::fake([
            '*/v1/authorize' => Http::response(['access_token' => 'token-test']),
            '*/v1/notification/*' => Http::response(['code' => 404], 404),
        ]);

        $this->processReceipt($receipt);

        $this->assertSame(Charge::STATUS_OPEN, $charge->refresh()->status);
        $this->assertSame(PaymentWebhookReceipt::STATUS_FAILED, $receipt->refresh()->status);
        $this->assertSame('Notificação não encontrada no provedor.', $receipt->last_error);
        $this->assertDatabaseCount('payments', 0, 'finance_fiscal');
        $this->assertDatabaseHas('domain_audit_events', [
            'action' => 'payment_webhook.token_not_found',
            'entity_id' => (string) $receipt->id,
        ], 'finance_fiscal');
        $this->assertStringNotContainsString(
            $token,
            DomainAuditEvent::query()
                ->where('action', 'payment_webhook.token_not_found')
                ->firstOrFail()
                ->toJson()
        );
    }

    public function test_invalid_json_and_unknown_provider_are_rejected(): void
    {
        config()->set('finance_fiscal.finance.payment_webhooks_enabled', true);

        $this->call(
            'POST',
            '/api/v1/webhooks/payments/efi',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{invalid'
        )->assertUnprocessable();

        $this->post('/api/v1/webhooks/payments/unknown', [
            'notification' => '09027955-5e06-4ff0-a9c7-46b47b8f1b27',
        ])->assertNotFound();

        $this->assertDatabaseCount('payment_webhook_receipts', 0, 'finance_fiscal');
    }

    public function test_webhook_rejects_unsupported_content_type_and_large_body(): void
    {
        config()->set('finance_fiscal.finance.payment_webhooks_enabled', true);

        $this->call(
            'POST',
            '/api/v1/webhooks/payments/efi',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            'notification=test'
        )->assertStatus(415);

        $this->call(
            'POST',
            '/api/v1/webhooks/payments/efi',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            'notification='.str_repeat('a', 4097)
        )->assertStatus(413);

        $this->assertDatabaseCount('payment_webhook_receipts', 0, 'finance_fiscal');
    }

    private function runEvents(PaymentWebhookReceipt $receipt, array $events): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*/v1/authorize' => Http::response(['access_token' => 'token-test']),
            '*/v1/notification/*' => Http::response(['code' => 200, 'data' => $events]),
        ]);

        $this->processReceipt($receipt);
    }

    private function protectedWebhookUrl(): string
    {
        return '/api/v1/webhooks/payments/efi/'.str_repeat('a', 64);
    }

    private function processReceipt(PaymentWebhookReceipt $receipt): void
    {
        (new ProcessEfiPaymentWebhook($receipt->id))->handle(
            app(EfiPaymentProvider::class),
            app(SyncChargeFromProvider::class),
            app(DomainAudit::class),
        );
    }

    private function event(
        int $id,
        string $status,
        ?int $value = null,
        string $chargeId = '900001',
    ): array {
        return [
            'id' => $id,
            'type' => 'charge',
            'identifiers' => ['charge_id' => $chargeId],
            'status' => ['current' => $status, 'previous' => null],
            'value' => $value,
            'received_by_bank_at' => $status === 'paid' ? '2026-08-07' : null,
            'created_at' => '2026-08-07 18:10:00',
        ];
    }

    private function receipt(
        string $token = '09027955-5e06-4ff0-a9c7-46b47b8f1b27',
    ): PaymentWebhookReceipt {
        return PaymentWebhookReceipt::query()
            ->create([
                'provider' => 'efi',
                'token_hash' => hash('sha256', $token),
                'token_encrypted' => Crypt::encryptString(
                    $token
                ),
                'status' => PaymentWebhookReceipt::STATUS_RECEIVED,
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
                    'description' => 'Serviço',
                    'unit_amount' => '850.00',
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
            'provider_charge_id' => (string) (900000 + $charge->id),
        ]);

        return $charge->refresh();
    }
}
