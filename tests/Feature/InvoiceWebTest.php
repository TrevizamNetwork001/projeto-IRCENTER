<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Modules\Finance\Actions\ActivateBillingContract;
use App\Modules\Finance\Actions\CreateBillingContract;
use App\Modules\Finance\Actions\GenerateInvoiceForContract;
use App\Modules\Finance\Models\BillingContract;
use App\Modules\Finance\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InvoiceWebTest extends TestCase
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
    }

    public function test_guest_cannot_access_invoices(): void
    {
        $this->get(
            route('finance.invoices.index')
        )->assertRedirect(
            route('login')
        );
    }

    public function test_authenticated_user_can_read_invoice_when_finance_is_disabled(): void
    {
        $viewer = $this->viewer();

        $contract = $this->activeContract();

        $invoice = app(
            GenerateInvoiceForContract::class
        )->handle(
            $contract->id,
            '2026-08'
        );

        $this->actingAs($viewer)
            ->get(route('finance.invoices.index'))
            ->assertOk()
            ->assertSee('Faturas')
            ->assertSee('Modo somente leitura');

        $this->actingAs($viewer)
            ->get(
                route(
                    'finance.invoices.show',
                    $invoice
                )
            )
            ->assertOk()
            ->assertSee('Link IP dedicado')
            ->assertSee('199,90');
    }

    public function test_non_administrator_cannot_generate_invoice(): void
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        $viewer = $this->viewer();

        $contract = $this->activeContract();

        $this->actingAs($viewer)
            ->post(
                route(
                    'finance.contracts.invoices.generate',
                    $contract
                ),
                [
                    'competence' => '2026-08',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'invoices',
            0,
            'finance_fiscal'
        );
    }

    public function test_administrator_cannot_generate_invoice_when_finance_is_disabled(): void
    {
        $admin = $this->admin();

        $contract = $this->activeContract();

        $this->actingAs($admin)
            ->post(
                route(
                    'finance.contracts.invoices.generate',
                    $contract
                ),
                [
                    'competence' => '2026-08',
                ]
            )
            ->assertStatus(503);

        $this->assertDatabaseCount(
            'invoices',
            0,
            'finance_fiscal'
        );
    }

    public function test_administrator_can_generate_invoice_idempotently_and_view_it(): void
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        $admin = $this->admin();

        $contract = $this->activeContract();

        $first = $this->actingAs($admin)
            ->post(
                route(
                    'finance.contracts.invoices.generate',
                    $contract
                ),
                [
                    'competence' => '2026-08',
                ]
            );

        $invoice = Invoice::query()
            ->firstOrFail();

        $first->assertRedirect(
            route(
                'finance.invoices.show',
                $invoice
            )
        );

        $this->actingAs($admin)
            ->post(
                route(
                    'finance.contracts.invoices.generate',
                    $contract
                ),
                [
                    'competence' => '2026-08',
                ]
            )
            ->assertRedirect(
                route(
                    'finance.invoices.show',
                    $invoice
                )
            );

        $this->assertDatabaseCount(
            'invoices',
            1,
            'finance_fiscal'
        );

        $this->assertSame(
            Invoice::STATUS_OPEN,
            $invoice->fresh()->status
        );

        $this->actingAs($admin)
            ->get(
                route(
                    'finance.invoices.show',
                    $invoice
                )
            )
            ->assertOk()
            ->assertSee('08/2026')
            ->assertSee('Link IP dedicado')
            ->assertSee('199,90')
            ->assertSee('Nenhuma cobrança associada');
    }

    private function activeContract(): BillingContract
    {
        $client = Client::factory()->create([
            'legal_name' =>
                'Cliente Financeiro LTDA',

            'trade_name' =>
                'Cliente Financeiro',

            'active' => true,
        ]);

        $contract = app(
            CreateBillingContract::class
        )->handle(
            clientId: $client->id,

            attributes: [
                'generation_day' => 5,
                'due_day' => 20,
                'billing_email_override' =>
                    'financeiro@example.test',
            ],

            items: [
                [
                    'service_code' => 'LINK-IP',
                    'description' =>
                        'Link IP dedicado',

                    'quantity' => '1',
                    'unit_amount' => '199.90',
                ],
            ],
        );

        return app(
            ActivateBillingContract::class
        )->handle(
            $contract->id
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
