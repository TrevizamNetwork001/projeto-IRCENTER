<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Fiscal\Models\FiscalIssuerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class FiscalIssuerConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', ['--database' => 'finance_fiscal', '--path' => 'database/migrations/finance_fiscal', '--force' => true]);
        config()->set('finance_fiscal.fiscal.enabled', true);
        Http::preventStrayRequests();
    }

    public function test_dashboard_and_create_page_guide_admin_when_issuer_is_missing(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('fiscal.dashboard'))->assertOk()->assertSee('Emitente pendente')->assertSee('Configurar emitente');
        $this->actingAs($admin)->get(route('fiscal.documents.create'))->assertOk()->assertSee('Emitente fiscal não configurado')->assertSee('Configurar emitente')->assertDontSee('name="issuer_id"', false);
    }

    public function test_only_administrator_can_access_or_update_issuer_configuration(): void
    {
        $operator = User::factory()->create(['role' => User::ROLE_OPERATOR, 'must_change_password' => false]);
        $this->actingAs($operator)->get(route('fiscal.issuer.edit'))->assertForbidden();
        $this->actingAs($operator)->put(route('fiscal.issuer.update'), $this->validPayload())->assertForbidden();
    }

    public function test_admin_can_create_active_valid_issuer_with_audit_events(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('fiscal.issuer.update'), $this->validPayload())->assertRedirect(route('fiscal.issuer.edit'));
        $this->assertDatabaseHas('fiscal_issuer_profiles', ['document' => '11222333000181', 'active' => true], 'finance_fiscal');
        $this->assertDatabaseHas('domain_audit_events', ['action' => 'fiscal.issuer.created', 'actor_user_id' => $admin->id], 'finance_fiscal');
        $this->assertDatabaseHas('domain_audit_events', ['action' => 'fiscal.issuer.activated', 'actor_user_id' => $admin->id], 'finance_fiscal');
        $this->assertSame(0, Http::recorded()->count());
    }

    public function test_invalid_issuer_cannot_be_activated(): void
    {
        $payload = $this->validPayload();
        $payload['document'] = '00000000000000';
        $payload['municipality_code'] = '';
        $this->actingAs($this->admin())->put(route('fiscal.issuer.update'), $payload)->assertSessionHasErrors(['document', 'municipality_code']);
        $this->assertDatabaseCount('fiscal_issuer_profiles', 0, 'finance_fiscal');
    }

    public function test_updating_active_issuer_keeps_single_active_profile_and_records_update(): void
    {
        $admin = $this->admin();
        FiscalIssuerProfile::query()->create($this->validPayload());
        FiscalIssuerProfile::query()->create(array_merge($this->validPayload('52998224725'), ['legal_name' => 'Perfil inativo', 'active' => false]));
        $payload = $this->validPayload();
        $payload['trade_name'] = 'Nome atualizado';
        $this->actingAs($admin)->put(route('fiscal.issuer.update'), $payload)->assertRedirect();
        $this->assertSame(1, FiscalIssuerProfile::query()->where('active', true)->count());
        $this->assertDatabaseHas('domain_audit_events', ['action' => 'fiscal.issuer.updated'], 'finance_fiscal');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'must_change_password' => false]);
    }

    private function validPayload(string $document = '11222333000181'): array
    {
        return ['legal_name' => 'Emitente de Teste LTDA', 'trade_name' => 'Emitente Teste', 'document' => $document, 'municipal_registration' => '12345', 'municipality_code' => '3550308', 'municipality' => 'São Paulo', 'state' => 'SP', 'postal_code' => '01001000', 'street' => 'Praça de Teste', 'address_number' => '1', 'address_complement' => null, 'district' => 'Centro', 'phone' => null, 'email' => null, 'active' => true];
    }
}
