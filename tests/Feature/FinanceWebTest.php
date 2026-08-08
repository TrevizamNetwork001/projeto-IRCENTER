<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Modules\Finance\Actions\CreateBillingContract;
use App\Modules\Finance\Models\BillingContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FinanceWebTest extends TestCase
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

        /*
         * Estado seguro padrão desta suíte.
         */
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

    public function test_guest_cannot_access_finance(): void
    {
        $this->get(
            route('finance.dashboard')
        )->assertRedirect(
            route('login')
        );

        $this->get(
            route('finance.contracts.index')
        )->assertRedirect(
            route('login')
        );
    }

    public function test_authenticated_user_can_read_finance_when_disabled(): void
    {
        $viewer = $this->viewer();

        $this->actingAs($viewer)
            ->get(route('finance.dashboard'))
            ->assertOk()
            ->assertSee('Financeiro')
            ->assertSee('Módulo em modo protegido');

        $this->actingAs($viewer)
            ->get(route('finance.contracts.index'))
            ->assertOk()
            ->assertSee('Contratos financeiros')
            ->assertSee('Operações bloqueadas');
    }

    public function test_non_administrator_cannot_create_financial_contract(): void
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        $viewer = $this->viewer();

        $client = Client::factory()->create([
            'active' => true,
        ]);

        $this->actingAs($viewer)
            ->post(
                route('finance.contracts.store'),
                $this->contractPayload($client)
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'billing_contracts',
            0,
            'finance_fiscal'
        );
    }

    public function test_administrator_write_is_blocked_while_finance_is_disabled(): void
    {
        $admin = $this->admin();

        $client = Client::factory()->create([
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('finance.contracts.create'))
            ->assertStatus(503);

        $this->actingAs($admin)
            ->post(
                route('finance.contracts.store'),
                $this->contractPayload($client)
            )
            ->assertStatus(503);

        $this->assertDatabaseCount(
            'billing_contracts',
            0,
            'finance_fiscal'
        );
    }

    public function test_administrator_can_create_and_view_contract_when_enabled(): void
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        $admin = $this->admin();

        $client = Client::factory()->create([
            'legal_name' =>
                'IRCENTER Telecom LTDA',

            'trade_name' =>
                'IRCENTER Telecom',

            'active' =>
                true,
        ]);

        $response = $this->actingAs($admin)
            ->post(
                route('finance.contracts.store'),
                $this->contractPayload($client)
            );

        $contract = BillingContract::query()
            ->firstOrFail();

        $response->assertRedirect(
            route(
                'finance.contracts.show',
                $contract
            )
        );

        $this->assertSame(
            BillingContract::STATUS_DRAFT,
            $contract->status
        );

        $this->assertSame(
            $client->id,
            $contract->core_client_id
        );

        $this->assertSame(
            1,
            $contract->items()->count()
        );

        $this->assertDatabaseHas(
            'billing_contracts',
            [
                'id' => $contract->id,
                'core_client_id' => $client->id,
                'generation_day' => 5,
                'due_day' => 20,
                'status' =>
                    BillingContract::STATUS_DRAFT,
            ],
            'finance_fiscal'
        );

        $this->actingAs($admin)
            ->get(
                route(
                    'finance.contracts.show',
                    $contract
                )
            )
            ->assertOk()
            ->assertSee('IRCENTER Telecom')
            ->assertSee('Link IP dedicado')
            ->assertSee('Rascunho');

        $this->actingAs($admin)
            ->get(route('finance.contracts.index'))
            ->assertOk()
            ->assertSee('IRCENTER Telecom');
    }

    public function test_administrator_can_activate_and_suspend_contract(): void
    {
        config()->set(
            'finance_fiscal.finance.enabled',
            true
        );

        $admin = $this->admin();

        $client = Client::factory()->create([
            'active' => true,
        ]);

        $contract = app(
            CreateBillingContract::class
        )->handle(
            clientId: $client->id,

            attributes: [
                'generation_day' => 5,
                'due_day' => 20,
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

            actorUserId: $admin->id,
        );

        $this->assertSame(
            BillingContract::STATUS_DRAFT,
            $contract->status
        );

        $this->actingAs($admin)
            ->post(
                route(
                    'finance.contracts.activate',
                    $contract
                )
            )
            ->assertRedirect();

        $contract->refresh();

        $this->assertSame(
            BillingContract::STATUS_ACTIVE,
            $contract->status
        );

        $this->actingAs($admin)
            ->post(
                route(
                    'finance.contracts.suspend',
                    $contract
                )
            )
            ->assertRedirect();

        $contract->refresh();

        $this->assertSame(
            BillingContract::STATUS_SUSPENDED,
            $contract->status
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

    /**
     * @return array<string, mixed>
     */
    private function contractPayload(
        Client $client
    ): array {
        return [
            'client_id' => $client->id,
            'generation_day' => 5,
            'due_day' => 20,
            'billing_email_override' => null,
            'starts_on' => '2026-08-01',
            'ends_on' => null,
            'service_code' => 'LINK-IP',
            'description' => 'Link IP dedicado',
            'quantity' => '1',
            'unit_amount' => '199.90',
        ];
    }
}
