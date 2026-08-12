<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Modules\Finance\Models\BillingItem;
use App\Modules\Fiscal\Actions\AuthorizeFiscalDocumentManually;
use App\Modules\Fiscal\Actions\CancelFiscalDraft;
use App\Modules\Fiscal\Actions\CreateFiscalDocument;
use App\Modules\Fiscal\Actions\PrepareFiscalDocument;
use App\Modules\Fiscal\Actions\ReturnFiscalDocumentToDraft;
use App\Modules\Fiscal\Enums\FiscalDocumentStatus;
use App\Modules\Fiscal\Models\FiscalCustomerProfile;
use App\Modules\Fiscal\Models\FiscalDocument;
use App\Modules\Fiscal\Models\FiscalIssuerProfile;
use App\Modules\Fiscal\Models\FiscalServiceProfile;
use App\Modules\Fiscal\Services\FiscalDocumentReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

final class FiscalManualOperationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', ['--database' => 'finance_fiscal', '--path' => 'database/migrations/finance_fiscal', '--force' => true]);
        config()->set('finance_fiscal.fiscal.enabled', true);
        Http::preventStrayRequests();
    }

    public function test_readiness_reports_ready_warning_and_blocked(): void
    {
        [$document, , $customer] = $this->draft();
        $service = app(FiscalDocumentReadinessService::class);
        $this->assertSame('READY', $service->evaluate($document)->status);
        $customer->update(['fiscal_email' => null]);
        $this->assertSame('WARNING', $service->evaluate($document->fresh())->status);
        $customer->update(['document' => null]);
        $result = $service->evaluate($document->fresh());
        $this->assertSame('BLOCKED', $result->status);
        $this->assertTrue(collect($result->issues)->contains('code', 'customer.document'));
    }

    public function test_manual_transitions_freeze_and_restore_snapshot_then_authorize(): void
    {
        [$document, $user, $customer, $service] = $this->draft();
        $ready = app(PrepareFiscalDocument::class)->execute($document, $user->id);
        $snapshot = $ready->customer_snapshot;
        $customer->update(['legal_name' => 'Nome alterado']);
        $service->update(['fiscal_description' => 'Serviço alterado']);
        $this->assertSame('Tomador Original', $ready->fresh()->customer_snapshot['legal_name']);

        $draft = app(ReturnFiscalDocumentToDraft::class)->execute($ready->fresh(), $user->id);
        $this->assertSame(FiscalDocumentStatus::Draft, $draft->status);
        $this->assertNull($draft->customer_snapshot);
        $customer->update(['legal_name' => $snapshot['legal_name']]);
        $service->update(['fiscal_description' => 'Serviço Fiscal']);
        $ready = app(PrepareFiscalDocument::class)->execute($draft, $user->id);
        $authorized = app(AuthorizeFiscalDocumentManually::class)->execute($ready, ['nfse_number' => 'NF-2026-15', 'access_key' => 'ABC123', 'verification_code' => 'VER-9', 'authorized_at' => '2026-08-12 10:00:00'], $user->id);
        $this->assertSame(FiscalDocumentStatus::Authorized, $authorized->status);
        $this->assertSame('manual', $authorized->emission_origin);
        $this->assertSame($user->id, $authorized->registered_by_user_id);
        $this->assertNotNull($authorized->authorized_at);
        $this->expectException(LogicException::class);
        $authorized->update(['services_amount' => '999.00']);
    }

    public function test_draft_can_be_cancelled_only_as_internal_document(): void
    {
        [$document, $user] = $this->draft();
        $cancelled = app(CancelFiscalDraft::class)->execute($document, $user->id);
        $this->assertSame(FiscalDocumentStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertDatabaseHas('domain_audit_events', ['action' => 'fiscal.document.internal_cancelled'], 'finance_fiscal');
    }

    public function test_http_flow_requires_confirmation_and_records_manual_nfse(): void
    {
        [$document, $user] = $this->draft();
        app(PrepareFiscalDocument::class)->execute($document, $user->id);
        $this->actingAs($user)->get(route('fiscal.dashboard'))->assertOk()->assertSee('Prontos para emissão');
        $this->actingAs($user)->get(route('fiscal.documents.show', $document))->assertOk()->assertSee('Dados para emissão manual')->assertSee('Copiar resumo para emissão');
        $route = route('fiscal.documents.authorize-manual', $document);
        $payload = ['nfse_number' => '100-2026', 'authorized_at' => '2026-08-12T10:00'];
        $this->actingAs($user)->post($route, $payload)->assertSessionHasErrors('confirmation');
        $this->actingAs($user)->post($route, $payload + ['confirmation' => '1'])->assertRedirect();
        $this->assertDatabaseHas('fiscal_documents', ['id' => $document->id, 'status' => 'authorized', 'nfse_number' => '100-2026', 'emission_origin' => 'manual'], 'finance_fiscal');
        $this->assertDatabaseHas('domain_audit_events', ['action' => 'fiscal.document.manual_authorized', 'actor_user_id' => $user->id], 'finance_fiscal');
    }

    public function test_private_xml_and_pdf_upload_hash_download_and_idor(): void
    {
        Storage::fake('local');
        [$document, $user] = $this->draft();
        $ready = app(PrepareFiscalDocument::class)->execute($document, $user->id);
        app(AuthorizeFiscalDocumentManually::class)->execute($ready, ['nfse_number' => '101', 'authorized_at' => now()], $user->id);

        $xml = '<?xml version="1.0" encoding="UTF-8"?><NFSe><Numero>101</Numero></NFSe>';
        $this->actingAs($user)->post(route('fiscal.documents.artifacts.store', $document), ['type' => 'manual_nfse_xml', 'artifact' => UploadedFile::fake()->createWithContent('nota.xml', $xml)])->assertRedirect();
        $artifact = $document->artifacts()->firstOrFail();
        Storage::disk('local')->assertExists($artifact->storage_path);
        $this->assertSame(hash('sha256', $xml), $artifact->sha256);
        $this->assertSame(strlen($xml), $artifact->size);
        $this->assertStringNotContainsString('public/', $artifact->storage_path);
        $this->actingAs($user)->get(route('fiscal.documents.artifacts.download', [$document, $artifact]))->assertOk();

        $pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF";
        $this->actingAs($user)->post(route('fiscal.documents.artifacts.store', $document), ['type' => 'manual_danfse_pdf', 'artifact' => UploadedFile::fake()->createWithContent('danfse.pdf', $pdf)])->assertRedirect();
        $this->assertSame(2, $document->artifacts()->count());
        $this->actingAs($user)->post(route('fiscal.documents.artifacts.store', $document), ['type' => 'manual_nfse_xml', 'artifact' => UploadedFile::fake()->createWithContent('ruim.xml', '<NFSe>')])->assertStatus(422);

        [$other] = $this->draft('manual-other');
        $this->actingAs($user)->get(route('fiscal.documents.artifacts.download', [$other, $artifact]))->assertNotFound();
    }

    public function test_create_ui_and_client_section_are_available_only_when_enabled(): void
    {
        [$document, $user, $customer] = $this->draft();
        $this->actingAs($user)->get(route('fiscal.documents.create', ['client_id' => $customer->core_client_id]))->assertOk()->assertSee('Novo documento fiscal');
        $this->actingAs($user)->get(route('clients.show', $customer->core_client_id))->assertOk()->assertSee('Cadastro e documentos fiscais')->assertSee($document->public_id);
        config()->set('finance_fiscal.fiscal.enabled', false);
        $this->actingAs($user)->get(route('fiscal.documents.show', $document))->assertNotFound();
    }

    private function draft(string $key = 'manual-test'): array
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'must_change_password' => false]);
        $client = Client::factory()->create();
        $item = BillingItem::query()->create(['name' => 'Link', 'description' => 'Link', 'default_amount' => '100.00', 'active' => true]);
        $issuer = FiscalIssuerProfile::query()->firstOrCreate(['document' => '11222333000181'], ['legal_name' => 'Emitente LTDA', 'municipality_code' => '3550308', 'municipality' => 'São Paulo', 'state' => 'SP', 'postal_code' => '01001000', 'street' => 'Praça da Sé', 'address_number' => '1', 'district' => 'Sé', 'active' => true]);
        $customer = FiscalCustomerProfile::query()->create(['core_client_id' => $client->id, 'document' => '52998224725', 'legal_name' => 'Tomador Original', 'fiscal_email' => 'fiscal@example.test', 'street' => 'Rua Teste', 'address_number' => '10', 'address_complement' => 'Sala 1', 'municipality' => 'São Paulo', 'municipality_code' => '3550308', 'state' => 'SP', 'country_code' => 'BR']);
        $service = FiscalServiceProfile::query()->create(['billing_item_id' => $item->id, 'fiscal_description' => 'Serviço Fiscal', 'national_service_code' => '010101', 'municipality_code' => '3550308', 'active' => true]);
        $document = app(CreateFiscalDocument::class)->execute(['core_client_id' => $client->id, 'fiscal_issuer_profile_id' => $issuer->id, 'fiscal_customer_profile_id' => $customer->id, 'billing_item_id' => $item->id, 'competence_date' => '2026-08-01', 'idempotency_key' => $key], [['fiscal_service_profile_id' => $service->id, 'billing_item_id' => $item->id, 'description' => 'Conectividade empresarial', 'quantity' => '1.0000', 'unit_amount' => '100.00']], $user->id);
        return [$document, $user, $customer, $service];
    }
}
