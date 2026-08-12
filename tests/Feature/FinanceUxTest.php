<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Modules\Finance\Actions\CreateBillingContract;
use App\Modules\Finance\Actions\CreateOneOffInvoice;
use App\Modules\Finance\Models\BillingItem;
use App\Modules\Finance\Models\BillingContract;
use App\Modules\Finance\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FinanceUxTest extends TestCase
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
        config()->set('finance_fiscal.finance.enabled', true);
        config()->set('finance_fiscal.finance.payment_provider', 'fake');
        config()->set('finance_fiscal.finance.payment_live_enabled', false);
    }

    public function test_admin_manages_catalog_and_viewer_cannot_mutate_it(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $viewer = $this->user(User::ROLE_VIEWER);
        $this->actingAs($admin)->post(route('finance.items.store'), [
            'name' => 'Suporte mensal', 'description' => 'Atendimento',
            'default_amount' => '199,90', 'active' => '1',
        ])->assertRedirect();
        $item = BillingItem::query()->firstOrFail();
        $this->assertSame('199.90', $item->default_amount);
        $this->actingAs($admin)->put(route('finance.items.update', $item), [
            'name' => 'Suporte premium', 'default_amount' => '249.90', 'active' => '1',
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('finance.items.toggle', $item))->assertRedirect();
        $this->assertFalse($item->fresh()->active);
        $this->actingAs($viewer)->patch(route('finance.items.toggle', $item))->assertForbidden();
    }

    public function test_contract_keeps_catalog_value_snapshot_after_catalog_changes(): void
    {
        $client = Client::factory()->create(['active' => true]);
        $item = BillingItem::query()->create(['name' => 'Link dedicado', 'default_amount' => '500.00', 'active' => true]);
        $contract = app(CreateBillingContract::class)->handle($client->id, ['generation_day' => 5, 'due_day' => 20], [[
            'billing_item_id' => $item->id, 'description' => $item->name,
            'quantity' => '2', 'unit_amount' => '450.00',
        ]]);
        $item->update(['default_amount' => '900.00', 'active' => false]);
        $this->assertSame('450.00', $contract->items->first()->unit_amount);
        $this->assertSame($item->id, $contract->items->first()->billing_item_id);
    }

    public function test_one_off_invoice_snapshots_item_and_creates_no_charge(): void
    {
        $client = Client::factory()->create(['active' => true]);
        $item = BillingItem::query()->create(['name' => 'Implantação', 'default_amount' => '300.00', 'active' => true]);
        $invoice = app(CreateOneOffInvoice::class)->handle($client->id, $item->id, '2', '275.00', now()->addDays(5)->toDateString());
        $this->assertSame(Invoice::SOURCE_ONE_OFF, $invoice->source);
        $this->assertSame('550.00', $invoice->total);
        $this->assertSame('Implantação', $invoice->items->first()->description);
        $this->assertDatabaseCount('charges', 0, 'finance_fiscal');
    }

    public function test_inactive_catalog_item_cannot_be_used_for_new_invoice(): void
    {
        $client = Client::factory()->create(['active' => true]);
        $item = BillingItem::query()->create(['name' => 'Antigo', 'default_amount' => '1.00', 'active' => false]);
        $this->expectException(\DomainException::class);
        app(CreateOneOffInvoice::class)->handle($client->id, $item->id, '1', '1.00', now()->addDay()->toDateString());
    }

    public function test_inactive_catalog_item_cannot_be_used_for_new_contract(): void
    {
        $client = Client::factory()->create(['active' => true]);
        $item = BillingItem::query()->create([
            'name' => 'Serviço descontinuado',
            'default_amount' => '100.00',
            'active' => false,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(CreateBillingContract::class)->handle(
            $client->id,
            ['generation_day' => 5, 'due_day' => 20],
            [[
                'billing_item_id' => $item->id,
                'description' => $item->name,
                'quantity' => '1',
                'unit_amount' => '100.00',
            ]]
        );
    }

    public function test_operational_pages_render_human_status_and_filters(): void
    {
        $viewer = $this->user(User::ROLE_VIEWER);
        $this->actingAs($viewer)->get(route('finance.items.index'))->assertOk()->assertSee('Itens de cobrança');
        $this->actingAs($viewer)->get(route('finance.invoices.index', ['charge' => 'without']))->assertOk()->assertSee('Faturas / Cobranças')->assertSee('Aguardando pagamento');
    }

    public function test_finance_clients_lists_one_row_per_client_and_highlights_active_configuration(): void
    {
        $viewer = $this->user(User::ROLE_VIEWER);
        $client = Client::factory()->create([
            'legal_name' => 'Cliente recorrente único',
            'trade_name' => null,
            'active' => true,
        ]);
        $item = BillingItem::query()->create([
            'name' => 'Consultoria mensal',
            'default_amount' => '500.00',
            'active' => true,
        ]);

        $historical = $this->contract($client, $item, '100.00');
        $historical->update(['status' => BillingContract::STATUS_SUSPENDED]);
        $current = $this->contract($client, $item, '500.00');
        $current->update(['status' => BillingContract::STATUS_ACTIVE]);

        $response = $this->actingAs($viewer)
            ->get(route('finance.clients.index'))
            ->assertOk()
            ->assertSee('Consultoria mensal')
            ->assertSee('1 ativa(s) + 1 histórica(s)')
            ->assertSee('R$ 500,00');

        $this->assertSame(
            1,
            substr_count($response->getContent(), 'Cliente recorrente único')
        );
    }

    public function test_finance_client_detail_hides_previous_configurations_and_uses_operational_language(): void
    {
        $viewer = $this->user(User::ROLE_VIEWER);
        $client = Client::factory()->create(['active' => true]);
        $item = BillingItem::query()->create([
            'name' => 'Link mensal',
            'default_amount' => '250.00',
            'active' => true,
        ]);
        $old = $this->contract($client, $item, '100.00');
        $old->update(['status' => BillingContract::STATUS_SUSPENDED]);
        $current = $this->contract($client, $item, '250.00');
        $current->update(['status' => BillingContract::STATUS_ACTIVE]);

        $this->actingAs($viewer)
            ->get(route('finance.clients.show', $client))
            ->assertOk()
            ->assertSee('Cobrança recorrente')
            ->assertSee('Automação de geração')
            ->assertSee('Desligada')
            ->assertSee('<details class="panel finance-history">', false)
            ->assertSee('Configurações anteriores (1)')
            ->assertDontSee('Detalhes técnicos');
    }

    public function test_client_without_recurrence_can_configure_it_and_start_one_off_invoice_preselected(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $client = Client::factory()->create(['active' => true]);

        $this->actingAs($admin)
            ->get(route('finance.clients.show', $client))
            ->assertOk()
            ->assertSee('Sem cobrança recorrente')
            ->assertSee('Configurar cobrança recorrente');

        $this->actingAs($admin)
            ->get(route('finance.invoices.create', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee('value="'.$client->id.'" selected', false);
    }

    public function test_finance_workspace_is_client_centered_and_keeps_secondary_pages_accessible(): void
    {
        $viewer = $this->user(User::ROLE_VIEWER);
        $client = Client::factory()->create(['legal_name' => 'TREVIZAM NETWORKS', 'trade_name' => null, 'active' => true]);
        $item = BillingItem::query()->create(['name' => 'Consultoria mensal', 'default_amount' => '500.00', 'active' => true]);
        $old = $this->contract($client, $item, '100.00');
        $old->update(['status' => BillingContract::STATUS_SUSPENDED]);
        $current = $this->contract($client, $item, '500.00');
        $current->update(['status' => BillingContract::STATUS_ACTIVE]);

        $response = $this->actingAs($viewer)
            ->get(route('finance.dashboard', ['client' => $client->id]))
            ->assertOk()
            ->assertSee('Cliente, recorrência, cobranças e pagamentos')
            ->assertSee('R$ 500,00 / mês')
            ->assertSee('Cobrança recorrente')
            ->assertSee('Configurações anteriores (1)')
            ->assertSee('Últimas cobranças')
            ->assertSee('Todas as cobranças')
            ->assertSee('Itens de cobrança')
            ->assertSee('Voltar aos clientes')
            ->assertDontSee('BillingContract')
            ->assertDontSee('Visão geral')
            ->assertDontSee('Provider:');

        $this->assertSame(2, substr_count($response->getContent(), 'TREVIZAM NETWORKS'));
        $this->actingAs($viewer)->get(route('finance.invoices.index'))->assertOk();
        $this->actingAs($viewer)->get(route('finance.items.index'))->assertOk();
    }

    public function test_viewer_cannot_edit_recurring_configuration(): void
    {
        $viewer = $this->user(User::ROLE_VIEWER);
        $client = Client::factory()->create(['active' => true]);
        $item = BillingItem::query()->create([
            'name' => 'Suporte',
            'default_amount' => '50.00',
            'active' => true,
        ]);
        $contract = $this->contract($client, $item, '50.00');

        $this->actingAs($viewer)
            ->get(route('finance.contracts.edit', $contract))
            ->assertForbidden();
        $this->actingAs($viewer)
            ->put(route('finance.contracts.update', $contract), [])
            ->assertForbidden();
    }

    private function contract(Client $client, BillingItem $item, string $amount): BillingContract
    {
        return app(CreateBillingContract::class)->handle(
            $client->id,
            ['generation_day' => 5, 'due_day' => 20],
            [[
                'billing_item_id' => $item->id,
                'description' => $item->name,
                'quantity' => '1',
                'unit_amount' => $amount,
            ]]
        );
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true, 'must_change_password' => false]);
    }
}
