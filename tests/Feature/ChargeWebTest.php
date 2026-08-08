<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Modules\Finance\Actions\ActivateBillingContract;
use App\Modules\Finance\Actions\CreateBillingContract;
use App\Modules\Finance\Actions\GenerateInvoiceForContract;
use App\Modules\Finance\Contracts\CorrelatablePaymentProvider;
use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Finance\Data\PaymentChargeRequest;
use App\Modules\Finance\Data\PaymentChargeResult;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ChargeWebTest extends TestCase
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
            false
        );

        config()->set(
            'finance_fiscal.finance.payment_provider',
            'fake'
        );

        config()->set(
            'finance_fiscal.finance.payment_live_enabled',
            false
        );

        config()->set(
            'finance_fiscal.finance.payment_webhooks_enabled',
            false
        );

        config()->set(
            'finance_fiscal.providers.efi.environment',
            'homologation'
        );
    }

    public function test_guest_cannot_create_charge(): void
    {
        $invoice = $this->invoice();

        $this->post(
            route(
                'finance.invoices.charges.store',
                $invoice
            ),
            [
                'method' =>
                    Charge::METHOD_BOLETO,
            ]
        )->assertRedirect(
            route('login')
        );
    }

    public function test_non_administrator_cannot_create_charge(): void
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        $invoice = $this->invoice();

        $this->actingAs($this->viewer())
            ->post(
                route(
                    'finance.invoices.charges.store',
                    $invoice
                ),
                [
                    'method' =>
                        Charge::METHOD_BOLETO,
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'charges',
            0,
            'finance_fiscal'
        );
    }

    public function test_administrator_cannot_create_charge_when_finance_is_disabled(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->admin())
            ->post(
                route(
                    'finance.invoices.charges.store',
                    $invoice
                ),
                [
                    'method' =>
                        Charge::METHOD_BOLETO,
                ]
            )
            ->assertStatus(503);

        $this->assertDatabaseCount(
            'charges',
            0,
            'finance_fiscal'
        );
    }

    public function test_administrator_can_create_fake_charge_and_view_it(): void
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        $invoice = $this->invoice();

        $response = $this->actingAs(
            $this->admin()
        )->post(
            route(
                'finance.invoices.charges.store',
                $invoice
            ),
            [
                'method' =>
                    Charge::METHOD_BOLETO_PIX,
            ]
        );

        $response->assertRedirect(
            route(
                'finance.invoices.show',
                $invoice
            )
        );

        $charge = Charge::query()
            ->firstOrFail();

        $this->assertSame(
            'fake',
            $charge->provider
        );

        $this->assertSame(
            Charge::STATUS_OPEN,
            $charge->status
        );

        $this->assertStringStartsWith(
            'fake_',
            (string) $charge
                ->provider_charge_id
        );

        $this->actingAs($this->admin())
            ->get(
                route(
                    'finance.invoices.show',
                    $invoice
                )
            )
            ->assertOk()
            ->assertSee('Fake · simulador local')
            ->assertSee(
                $charge->provider_charge_id
            );
    }

    public function test_duplicate_web_submission_is_idempotent(): void
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        $admin = $this->admin();
        $invoice = $this->invoice();

        $payload = [
            'method' =>
                Charge::METHOD_BOLETO,
        ];

        $this->actingAs($admin)
            ->post(
                route(
                    'finance.invoices.charges.store',
                    $invoice
                ),
                $payload
            )
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(
                route(
                    'finance.invoices.charges.store',
                    $invoice
                ),
                $payload
            )
            ->assertRedirect();

        $this->assertDatabaseCount(
            'charges',
            1,
            'finance_fiscal'
        );
    }

    public function test_live_provider_is_hard_blocked_by_web_even_when_live_flag_is_enabled(): void
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        config()->set(
            'finance_fiscal.finance.payment_provider',
            'efi'
        );

        config()->set(
            'finance_fiscal.finance.payment_live_enabled',
            true
        );

        config()->set(
            'finance_fiscal.providers.efi.environment',
            'production'
        );

        $this->app->forgetInstance(
            PaymentProvider::class
        );

        $invoice = $this->invoice();

        $this->actingAs($this->admin())
            ->post(
                route(
                    'finance.invoices.charges.store',
                    $invoice
                ),
                [
                    'method' =>
                        Charge::METHOD_BOLETO,

                    'confirm_provider_submission' =>
                        '1',
                ]
            )
            ->assertStatus(503);

        $this->assertDatabaseCount(
            'charges',
            0,
            'finance_fiscal'
        );
    }

    public function test_uncertain_charge_can_be_reconciled_without_new_creation(): void
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        $provider =
            new class implements
                PaymentProvider,
                CorrelatablePaymentProvider
            {
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
                    throw new \RuntimeException(
                        'createCharge nao deve '
                        .'ser executado.'
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
                    return new PaymentChargeResult(
                        providerChargeId:
                            'probe-123',

                        status:
                            Charge::STATUS_OPEN,

                        checkoutUrl:
                            'https://example.test/'
                            .'charge/123',

                        pixCopyPaste:
                            '000201PROBE123',
                    );
                }

                public function cancelCharge(
                    string $providerChargeId,
                    string $idempotencyKey,
                ): PaymentChargeResult {
                    throw new \RuntimeException(
                        'cancel nao deve '
                        .'ser executado.'
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

        $this->app->instance(
            PaymentProvider::class,
            $provider
        );

        $invoice = $this->invoice();

        $charge = Charge::query()->create([
            'invoice_id' =>
                $invoice->id,

            'provider' =>
                'probe',

            'method' =>
                Charge::METHOD_BOLETO,

            'status' =>
                Charge::STATUS_SUBMISSION_UNKNOWN,

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

        $this->actingAs($this->admin())
            ->post(
                route(
                    'finance.charges.reconcile',
                    $charge
                )
            )
            ->assertRedirect(
                route(
                    'finance.invoices.show',
                    $invoice
                )
            );

        $charge->refresh();

        $this->assertSame(
            Charge::STATUS_OPEN,
            $charge->status
        );

        $this->assertSame(
            'probe-123',
            $charge->provider_charge_id
        );

        $this->assertSame(
            '000201PROBE123',
            $charge->provider_pix_copy_paste
        );
    }

    private function invoice(): Invoice
    {
        $client = Client::factory()->create([
            'legal_name' =>
                'Cliente Cobranca LTDA',

            'trade_name' =>
                'Cliente Cobranca',

            'active' =>
                true,
        ]);

        $contract = app(
            CreateBillingContract::class
        )->handle(
            clientId:
                $client->id,

            attributes: [
                'generation_day' => 5,
                'due_day' => 20,
                'billing_email_override' =>
                    'financeiro@example.test',
            ],

            items: [
                [
                    'service_code' =>
                        'LINK-IP',

                    'description' =>
                        'Link IP dedicado',

                    'quantity' =>
                        '1',

                    'unit_amount' =>
                        '199.90',
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
            '2026-08'
        );
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'must_change_password' => false,
        ]);
    }

    private function viewer(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_VIEWER,
            'active' => true,
            'must_change_password' => false,
        ]);
    }
}
