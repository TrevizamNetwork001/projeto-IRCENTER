<?php

namespace Tests\Feature\Portal;

use App\Models\Client;
use App\Models\ClientContact;
use App\Modules\Finance\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PortalLoginTest extends TestCase
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
    }

    private function contact(Client $client, string $email, string $password): ClientContact
    {
        return ClientContact::query()->create([
            'client_id' => $client->id,
            'type' => ClientContact::TYPE_GENERAL,
            'name' => 'Contato de teste',
            'email' => $email,
            'active' => true,
            'password' => $password,
            'must_change_password' => false,
        ]);
    }

    public function test_guest_cannot_access_invoices(): void
    {
        $this->get(route('portal.invoices.index'))
            ->assertRedirect(route('portal.login'));
    }

    public function test_contact_can_login_with_correct_credentials(): void
    {
        $client = Client::factory()->create();
        $this->contact($client, 'contato@cliente.com', 'senha-forte-123');

        $this->post(route('portal.login.store'), [
            'email' => 'contato@cliente.com',
            'password' => 'senha-forte-123',
        ])->assertRedirect(route('portal.invoices.index'));

        $this->assertAuthenticated('client');
    }

    public function test_contact_cannot_login_with_wrong_password(): void
    {
        $client = Client::factory()->create();
        $this->contact($client, 'contato@cliente.com', 'senha-forte-123');

        $this->post(route('portal.login.store'), [
            'email' => 'contato@cliente.com',
            'password' => 'senha-errada',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('client');
    }

    public function test_inactive_contact_cannot_login(): void
    {
        $client = Client::factory()->create();
        $contact = $this->contact($client, 'contato@cliente.com', 'senha-forte-123');
        $contact->update(['active' => false]);

        $this->post(route('portal.login.store'), [
            'email' => 'contato@cliente.com',
            'password' => 'senha-forte-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('client');
    }

    public function test_contact_only_sees_own_client_invoices(): void
    {
        $clientA = Client::factory()->create();
        $clientB = Client::factory()->create();

        $contactA = $this->contact($clientA, 'a@clientea.com', 'senha-forte-123');

        Invoice::query()->create([
            'core_client_id' => $clientA->id,
            'client_legal_name_snapshot' => $clientA->legal_name,
            'client_document_snapshot' => '00000000000000',
            'source' => Invoice::SOURCE_ONE_OFF,
            'competence_month' => now()->format('Y-m'),
            'issued_on' => now(),
            'due_on' => now()->addDays(10),
            'currency' => 'BRL',
            'status' => Invoice::STATUS_OPEN,
            'subtotal' => '100.00',
            'discount' => '0.00',
            'total' => '100.00',
        ]);

        Invoice::query()->create([
            'core_client_id' => $clientB->id,
            'client_legal_name_snapshot' => $clientB->legal_name,
            'client_document_snapshot' => '11111111111111',
            'source' => Invoice::SOURCE_ONE_OFF,
            'competence_month' => now()->format('Y-m'),
            'issued_on' => now(),
            'due_on' => now()->addDays(10),
            'currency' => 'BRL',
            'status' => Invoice::STATUS_OPEN,
            'subtotal' => '200.00',
            'discount' => '0.00',
            'total' => '200.00',
        ]);

        $this->actingAs($contactA, 'client')
            ->get(route('portal.invoices.index'))
            ->assertOk()
            ->assertSee('100,00')
            ->assertDontSee('200,00');
    }
}
