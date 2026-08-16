<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Modules\Finance\Models\BillingItem;
use App\Modules\Fiscal\Actions\CreateFiscalDocument;
use App\Modules\Fiscal\Actions\PrepareFiscalDocument;
use App\Modules\Fiscal\Contracts\NfseProvider;
use App\Modules\Fiscal\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Infrastructure\DisabledNfseProvider;
use App\Modules\Fiscal\Infrastructure\FakeNfseProvider;
use App\Modules\Fiscal\Models\FiscalCustomerProfile;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalIdempotency;
use App\Modules\Fiscal\Models\FiscalIssuerProfile;
use App\Modules\Fiscal\Models\FiscalServiceProfile;
use App\Modules\Fiscal\Services\FiscalArtifactStore;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

final class FiscalFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', ['--database' => 'finance_fiscal', '--path' => 'database/migrations/finance_fiscal', '--force' => true]);
        Http::preventStrayRequests();
    }

    public function test_fiscal_is_hidden_and_routes_are_blocked_by_default(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'must_change_password' => false]);
        $this->actingAs($user)->get('/fiscal')->assertNotFound();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('>Fiscal<', false);
        $this->assertFalse(config('finance_fiscal.fiscal.enabled'));
        $this->expectException(LogicException::class);
        app(CreateFiscalDocument::class)->execute([], []);
    }

    public function test_enabled_dashboard_is_internal_homologation_ui(): void
    {
        config()->set('finance_fiscal.fiscal.enabled', true);
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'must_change_password' => false]);
        $this->actingAs($user)->get('/fiscal')->assertOk()->assertSee('Modo de emissão: Manual')->assertSee('Nenhum documento fiscal')->assertDontSee('Emitir NFS-e');
    }

    public function test_customer_profile_requires_authorization_and_validation(): void
    {
        config()->set('finance_fiscal.fiscal.enabled', true);
        $client = Client::factory()->create();
        $viewer = User::factory()->create(['role' => User::ROLE_VIEWER, 'must_change_password' => false]);
        $this->actingAs($viewer)->get(route('clients.fiscal.edit', $client))->assertForbidden();
        $this->actingAs($viewer)->put(route('clients.fiscal.update', $client), ['country_code' => 'BR'])->assertForbidden();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'must_change_password' => false]);
        $this->actingAs($admin)->put(route('clients.fiscal.update', $client), ['document' => '11111111111', 'municipality_code' => '12', 'country_code' => 'BR'])->assertSessionHasErrors(['document', 'municipality_code']);
        $this->actingAs($admin)->put(route('clients.fiscal.update', $client), ['legal_name' => 'Tomador Fiscal', 'document' => '52998224725', 'municipality_code' => '3550308', 'country_code' => 'br'])->assertRedirect(route('clients.show', $client));
        $this->assertDatabaseHas('fiscal_customer_profiles', ['core_client_id' => $client->id, 'document' => '52998224725', 'country_code' => 'BR'], 'finance_fiscal');
    }

    public function test_draft_calculates_exact_decimals_and_financial_links_are_optional(): void
    {
        config()->set('finance_fiscal.fiscal.enabled', true);
        [$issuer, $customer, $service] = $this->profiles();
        $document = app(CreateFiscalDocument::class)->execute($this->documentData($issuer, $customer), [['fiscal_service_profile_id' => $service->id, 'description' => 'Conectividade', 'quantity' => '2.5000', 'unit_amount' => '10.10']]);
        $this->assertSame(FiscalDocumentStatus::Draft, $document->status);
        $this->assertSame('25.25', $document->services_amount);
        $this->assertSame('24.25', $document->net_amount);
        $this->assertNull($document->invoice_id);
        $this->assertNull($document->charge_id);
        $this->assertSame('25.25', $document->items->first()->total_amount);
        $this->assertDatabaseHas('fiscal_idempotencies', ['operation' => 'create', 'idempotency_key' => 'fiscal-test-1'], 'finance_fiscal');
    }

    public function test_invalid_transition_is_blocked(): void
    {
        $document = new FiscalDocument(['status' => FiscalDocumentStatus::Draft]);
        $this->expectException(LogicException::class);
        $document->transitionTo(FiscalDocumentStatus::Authorized);
    }

    public function test_preparation_freezes_all_snapshots_against_profile_changes(): void
    {
        config()->set('finance_fiscal.fiscal.enabled', true);
        [$issuer, $customer, $service] = $this->profiles();
        $document = app(CreateFiscalDocument::class)->execute($this->documentData($issuer, $customer), [['fiscal_service_profile_id' => $service->id, 'description' => 'Serviço original', 'quantity' => '1', 'unit_amount' => '100.00']]);
        $document = app(PrepareFiscalDocument::class)->execute($document);
        $customer->update(['legal_name' => 'Nome novo']);
        $service->update(['fiscal_description' => 'Descrição nova']);
        $fresh = $document->fresh();
        $this->assertSame('Tomador Original', $fresh->customer_snapshot['legal_name']);
        $this->assertSame('Serviço Fiscal Original', $fresh->service_snapshot[0]['classification']['fiscal_description']);
        $this->assertNotNull($fresh->issuer_snapshot);
        $this->assertNotNull($fresh->values_snapshot);
        $this->assertNotNull($fresh->tax_snapshot);
        $this->expectException(LogicException::class);
        $fresh->forceFill(['customer_snapshot' => ['tampered' => true]])->save();
    }

    public function test_database_idempotency_constraint_prevents_duplicate_operation(): void
    {
        config()->set('finance_fiscal.fiscal.enabled', true);
        [$issuer, $customer, $service] = $this->profiles();
        $document = app(CreateFiscalDocument::class)->execute($this->documentData($issuer, $customer), [['fiscal_service_profile_id' => $service->id, 'description' => 'Serviço', 'quantity' => '1', 'unit_amount' => '10']]);
        $this->expectException(QueryException::class);
        FiscalIdempotency::query()->create(['fiscal_document_id' => $document->id, 'operation' => 'create', 'idempotency_key' => 'fiscal-test-1', 'provider' => 'fake']);
    }

    public function test_fake_and_disabled_providers_never_use_network(): void
    {
        $fake = new FakeNfseProvider();
        $document = new FiscalDocument(['status' => FiscalDocumentStatus::Draft]);
        $this->assertTrue($fake->validate($document)['valid']);
        $this->assertTrue($fake->issue($document, 'key')['simulated']);
        $this->assertSame([], $fake->downloadArtifacts($document));
        $this->assertInstanceOf(FakeNfseProvider::class, app(NfseProvider::class));
        $this->expectException(LogicException::class);
        (new DisabledNfseProvider())->issue($document, 'key');
    }

    public function test_artifacts_are_private_and_store_only_hash_and_metadata(): void
    {
        Storage::fake('local');
        config()->set('finance_fiscal.fiscal.enabled', true);
        [$issuer, $customer, $service] = $this->profiles();
        $document = app(CreateFiscalDocument::class)->execute($this->documentData($issuer, $customer), [['fiscal_service_profile_id' => $service->id, 'description' => 'Serviço', 'quantity' => '1', 'unit_amount' => '10']]);
        $artifact = app(FiscalArtifactStore::class)->store($document, 'dps_xml', '<xml>simulado</xml>', 'application/xml');
        Storage::disk('local')->assertExists($artifact->storage_path);
        $this->assertSame(hash('sha256', '<xml>simulado</xml>'), $artifact->sha256);
        $this->assertSame('local', $artifact->storage_disk);
        $this->assertStringNotContainsString('public/', $artifact->storage_path);
    }

    private function profiles(): array
    {
        $client = Client::factory()->create();
        $item = BillingItem::query()->create(['name' => 'Link', 'description' => 'Link', 'default_amount' => '100.00', 'active' => true]);
        $issuer = FiscalIssuerProfile::query()->create(['legal_name' => 'Emitente LTDA', 'document' => '11222333000181', 'municipality_code' => '3550308', 'municipality' => 'São Paulo', 'state' => 'SP', 'postal_code' => '01001000', 'street' => 'Praça da Sé', 'address_number' => '1', 'district' => 'Sé', 'tax_settings' => ['regime' => 'simples'], 'active' => true]);
        $customer = FiscalCustomerProfile::query()->create(['core_client_id' => $client->id, 'document' => '52998224725', 'legal_name' => 'Tomador Original', 'municipality' => 'São Paulo', 'municipality_code' => '3550308', 'state' => 'SP', 'country_code' => 'BR']);
        $service = FiscalServiceProfile::query()->create(['billing_item_id' => $item->id, 'fiscal_description' => 'Serviço Fiscal Original', 'national_service_code' => '010101', 'iss_rate' => '2.0000', 'active' => true]);
        return [$issuer, $customer, $service];
    }

    private function documentData(FiscalIssuerProfile $issuer, FiscalCustomerProfile $customer): array
    {
        return ['core_client_id' => $customer->core_client_id, 'fiscal_issuer_profile_id' => $issuer->id, 'fiscal_customer_profile_id' => $customer->id, 'competence_date' => '2026-08-01', 'discount_amount' => '1.00', 'idempotency_key' => 'fiscal-test-1', 'summary' => 'Rascunho interno'];
    }
}
