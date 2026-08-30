<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use App\Modules\Finance\Models\BillingContract;
use App\Modules\Finance\Models\BillingContractItem;
use App\Modules\Finance\Models\Charge;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Finance\Models\InvoiceItem;
use App\Modules\Scheduling\Models\{Appointment, AvailabilityRule, EventType};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2eSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['Administrador E2E', 'admin@e2e.example.test', 'E2e-Admin-2026!', User::ROLE_ADMIN],
            ['Operador E2E', 'operator@e2e.example.test', 'E2e-Operator-2026!', User::ROLE_OPERATOR],
            ['Viewer E2E', 'viewer@e2e.example.test', 'E2e-Viewer-2026!', User::ROLE_VIEWER],
        ];

        foreach ($users as [$name, $email, $password, $role]) {
            User::query()->updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => $role,
                'active' => true,
                'must_change_password' => false,
                'password_changed_at' => now(),
            ]);
        }

        $eventType = EventType::query()->updateOrCreate(['slug' => 'consultoria-e2e'], [
            'name' => 'Consultoria E2E', 'description' => 'Serviço sintético para validação em navegador.',
            'duration_minutes' => 60, 'slot_interval_minutes' => 60, 'location_type' => 'online',
            'location_value' => 'Sala virtual E2E', 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
            'minimum_notice_minutes' => 0, 'maximum_days_ahead' => 90, 'active' => true,
        ]);
        foreach ([1, 2, 3, 4, 5] as $day) {
            AvailabilityRule::query()->updateOrCreate(['event_type_id' => $eventType->id, 'day_of_week' => $day, 'start_time' => '09:00'], ['end_time' => '18:00', 'timezone' => 'America/Sao_Paulo', 'active' => true]);
        }
        foreach ([['01K3CANCEA0000000000000000', 'cancel'], ['01K3RESCHEDA00000000000000', 'reschedule']] as [$publicId, $kind]) {
            Appointment::query()->updateOrCreate(['public_id' => $publicId], [
                'event_type_id' => $eventType->id, 'scheduled_start_at' => now()->next('Monday')->setTime(17, 0)->utc(),
                'scheduled_end_at' => now()->next('Monday')->setTime(18, 0)->utc(), 'timezone' => 'America/Sao_Paulo',
                'status' => 'confirmed', 'guest_name' => ucfirst($kind).' E2E', 'guest_email' => $kind.'@example.test',
                'cancellation_token_hash' => hash('sha256', str_repeat('a', 64)),
                'reschedule_token_hash' => hash('sha256', str_repeat('b', 64)),
            ]);
        }

        $clientA = Client::query()->updateOrCreate(['document' => '11222333000181'], [
            'legal_name' => 'Cliente Teste A LTDA',
            'trade_name' => 'Cliente Teste A',
            'email' => 'financeiro-a@example.test',
            'phone' => '11999990001',
            'website' => 'https://a.example.test',
            'postal_code' => '01001000',
            'street' => 'Praça E2E',
            'address_number' => '100',
            'district' => 'Centro de Testes',
            'city' => 'São Paulo',
            'state' => 'SP',
            'country' => 'BR',
            'active' => true,
        ]);

        Client::query()->updateOrCreate(['document' => '52998224725'], [
            'legal_name' => 'Cliente Teste B',
            'trade_name' => 'Cliente Teste B',
            'email' => 'contato-b@example.invalid',
            'city' => 'Curitiba',
            'state' => 'PR',
            'country' => 'BR',
            'active' => true,
        ]);

        $contract = BillingContract::query()->updateOrCreate(
            ['public_id' => '01JH11E2ECONTRACTTEST0001'],
            [
                'core_client_id' => $clientA->id,
                'client_code_snapshot' => $clientA->client_code,
                'client_legal_name_snapshot' => $clientA->legal_name,
                'client_trade_name_snapshot' => $clientA->trade_name,
                'client_document_snapshot' => $clientA->document,
                'frequency' => BillingContract::FREQUENCY_MONTHLY,
                'generation_day' => 5,
                'due_day' => 20,
                'currency' => 'BRL',
                'status' => BillingContract::STATUS_ACTIVE,
                'billing_email_override' => 'financeiro-a@example.test',
                'auto_charge' => false,
                'send_email' => false,
                'starts_on' => '2026-08-01',
            ],
        );

        $contractItem = BillingContractItem::query()->updateOrCreate(
            ['billing_contract_id' => $contract->id, 'service_code' => 'E2E-IP'],
            ['description' => 'Serviço sintético E2E', 'quantity' => 1, 'unit_amount' => 199.90, 'active' => true],
        );

        $invoice = Invoice::query()->updateOrCreate(
            ['generation_key' => 'e2e-contract-2026-08'],
            [
                'public_id' => '01JH11E2EINVOICETEST00001',
                'billing_contract_id' => $contract->id,
                'core_client_id' => $clientA->id,
                'client_code_snapshot' => $clientA->client_code,
                'client_legal_name_snapshot' => $clientA->legal_name,
                'client_trade_name_snapshot' => $clientA->trade_name,
                'client_document_snapshot' => $clientA->document,
                'billing_email_snapshot' => 'financeiro-a@example.test',
                'source' => Invoice::SOURCE_RECURRING,
                'competence_month' => '2026-08-01',
                'issued_on' => '2026-08-05',
                'due_on' => '2026-08-20',
                'currency' => 'BRL',
                'status' => Invoice::STATUS_OPEN,
                'subtotal' => 199.90,
                'discount' => 0,
                'total' => 199.90,
            ],
        );

        InvoiceItem::query()->updateOrCreate(
            ['invoice_id' => $invoice->id, 'service_code' => 'E2E-IP'],
            [
                'billing_contract_item_id' => $contractItem->id,
                'description' => 'Serviço sintético E2E',
                'quantity' => 1,
                'unit_amount' => 199.90,
                'line_total' => 199.90,
            ],
        );

        Charge::query()->updateOrCreate(
            ['idempotency_key' => 'e2e-charge-2026-08'],
            [
                'public_id' => '01JH11E2ECHARGETEST000001',
                'invoice_id' => $invoice->id,
                'provider' => 'fake',
                'method' => Charge::METHOD_PIX,
                'status' => Charge::STATUS_OPEN,
                'provider_charge_id' => 'fake-e2e-001',
                'amount' => 199.90,
                'currency' => 'BRL',
                'due_on' => '2026-08-20',
                'provider_pix_copy_paste' => 'PIX-SINTETICO-SEM-VALOR',
            ],
        );
    }
}
