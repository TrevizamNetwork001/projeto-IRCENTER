<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Modules\Finance\Actions\CreateBillingContract;
use App\Modules\Finance\Actions\CreateOneOffInvoice;
use App\Modules\Finance\Models\BillingItem;
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

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'active' => true, 'must_change_password' => false]);
    }
}
