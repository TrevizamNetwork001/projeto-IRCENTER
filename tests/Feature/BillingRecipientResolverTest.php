<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientContact;
use App\Modules\Finance\Actions\CreateBillingContract;
use App\Modules\Finance\Services\BillingRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use InvalidArgumentException;
use Tests\TestCase;

class BillingRecipientResolverTest extends TestCase
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
    }

    public function test_contract_override_has_priority(): void
    {
        $client = $this->client();

        $this->contact(
            $client,
            ClientContact::TYPE_FINANCIAL,
            'financeiro@cliente.test'
        );

        $contract = $this->contract(
            $client,
            [
                'billing_email_override' =>
                    'contrato@cliente.test',
            ]
        );

        $this->assertSame(
            'contrato@cliente.test',
            app(BillingRecipientResolver::class)
                ->resolve($contract)
        );
    }

    public function test_primary_financial_contact_is_used(): void
    {
        $client = $this->client();

        $this->contact(
            $client,
            ClientContact::TYPE_FINANCIAL,
            'secundario@cliente.test',
            false
        );

        $this->contact(
            $client,
            ClientContact::TYPE_FINANCIAL,
            'principal@cliente.test',
            true
        );

        $contract = $this->contract($client);

        $this->assertSame(
            'principal@cliente.test',
            app(BillingRecipientResolver::class)
                ->resolve($contract)
        );
    }

    public function test_non_primary_financial_contact_is_not_used(): void
    {
        $client = $this->client();

        $this->contact(
            $client,
            ClientContact::TYPE_FINANCIAL,
            'secundario@cliente.test',
            false
        );

        $this->assertNull(
            app(BillingRecipientResolver::class)
                ->resolve($this->contract($client))
        );
    }

    public function test_inactive_primary_contact_is_ignored(): void
    {
        $client = $this->client();

        ClientContact::query()->create([
            'client_id' => $client->id,
            'type' =>
                ClientContact::TYPE_FINANCIAL,
            'email' => 'inativo@cliente.test',
            'is_primary' => true,
            'active' => false,
        ]);

        $contract = $this->contract($client);

        $this->assertNull(
            app(BillingRecipientResolver::class)
                ->resolve($contract)
        );
    }

    public function test_legacy_client_email_is_not_used(): void
    {
        $client = $this->client([
            'email' => 'legacy@cliente.test',
        ]);

        $contract = $this->contract($client);

        $this->assertNull(
            app(BillingRecipientResolver::class)
                ->resolve($contract)
        );
    }

    public function test_general_contact_requires_explicit_fallback(): void
    {
        $client = $this->client();

        $this->contact(
            $client,
            ClientContact::TYPE_GENERAL,
            'geral@cliente.test',
            true
        );

        $contract = $this->contract($client);

        $resolver = app(
            BillingRecipientResolver::class
        );

        $this->assertNull(
            $resolver->resolve($contract)
        );

        config()->set(
            'finance_fiscal.finance.'
            .'allow_general_email_fallback',
            true
        );

        $this->assertSame(
            'geral@cliente.test',
            $resolver->resolve($contract)
        );
    }

    public function test_only_primary_financial_is_used_among_two_contacts(): void
    {
        $client = $this->client();

        $this->contact(
            $client,
            ClientContact::TYPE_FINANCIAL,
            'nao-principal@cliente.test',
            false
        );
        $this->contact(
            $client,
            ClientContact::TYPE_FINANCIAL,
            'principal@cliente.test',
            true
        );

        $this->assertSame(
            'principal@cliente.test',
            app(BillingRecipientResolver::class)
                ->resolve($this->contract($client))
        );
    }

    public function test_duplicate_primaries_are_resolved_deterministically(): void
    {
        $client = $this->client();

        $first = $this->contact(
            $client,
            ClientContact::TYPE_FINANCIAL,
            'primeiro@cliente.test',
            true
        );
        $this->contact(
            $client,
            ClientContact::TYPE_FINANCIAL,
            'segundo@cliente.test',
            true
        );

        $this->assertSame(
            $first->email,
            app(BillingRecipientResolver::class)
                ->resolve($this->contract($client))
        );
    }

    public function test_invalid_contract_override_is_rejected(): void
    {
        $client = $this->client();

        $this->expectException(
            InvalidArgumentException::class
        );

        $this->contract(
            $client,
            [
                'billing_email_override' =>
                    'nao-e-email',
            ]
        );
    }

    private function client(
        array $attributes = [],
    ): Client {
        return Client::factory()->create(
            array_merge(
                [
                    'active' => true,
                ],
                $attributes
            )
        );
    }

    private function contact(
        Client $client,
        string $type,
        string $email,
        bool $primary = false,
    ): ClientContact {
        return ClientContact::query()->create([
            'client_id' => $client->id,
            'type' => $type,
            'email' => $email,
            'is_primary' => $primary,
            'active' => true,
        ]);
    }

    private function contract(
        Client $client,
        array $attributes = [],
    ) {
        return app(CreateBillingContract::class)
            ->handle(
                clientId: $client->id,
                attributes: $attributes,
                items: [
                    [
                        'description' => 'Serviço',
                        'unit_amount' => '100.00',
                    ],
                ],
            );
    }
}
