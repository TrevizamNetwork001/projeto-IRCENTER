<?php

namespace Tests\Feature;

use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Finance\Data\PaymentChargeRequest;
use App\Modules\Finance\Infrastructure\EfiPaymentProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

class EfiPaymentProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config()->set(
            'finance_fiscal.providers.efi.environment',
            'homologation'
        );

        config()->set(
            'finance_fiscal.providers.efi.client_id',
            'sandbox-client'
        );

        config()->set(
            'finance_fiscal.providers.efi.client_secret',
            'sandbox-secret'
        );

        config()->set(
            'finance_fiscal.providers.efi.notification_url',
            null
        );

        config()->set(
            'finance_fiscal.finance.payment_live_enabled',
            false
        );
    }

    public function test_homologation_is_not_live(): void
    {
        $this->assertFalse(
            app(EfiPaymentProvider::class)
                ->isLive()
        );
    }

    public function test_container_can_resolve_efi_provider(): void
    {
        config()->set(
            'finance_fiscal.finance.payment_provider',
            'efi'
        );

        app()->forgetInstance(
            PaymentProvider::class
        );

        $provider = app(
            PaymentProvider::class
        );

        $this->assertInstanceOf(
            EfiPaymentProvider::class,
            $provider
        );

        $this->assertFalse(
            $provider->isLive()
        );
    }

    public function test_create_charge_uses_oauth_and_maps_bolix(): void
    {
        Http::fake([
            'https://cobrancas-h.api.efipay.com.br/v1/authorize'
                => Http::response([
                    'access_token' => 'token-test',
                    'token_type' => 'Bearer',
                    'expires_in' => 600,
                ]),

            'https://cobrancas-h.api.efipay.com.br/v1/charge/one-step'
                => Http::response([
                    'code' => 200,
                    'data' => [
                        'charge_id' => 1234567,
                        'status' => 'waiting',
                        'link' =>
                            'https://boleto.test/123',
                        'pix' => [
                            'qrcode' =>
                                '000201010212TESTE',
                        ],
                    ],
                ]),
        ]);

        $result = app(
            EfiPaymentProvider::class
        )->createCharge(
            $this->request()
        );

        $this->assertSame(
            '1234567',
            $result->providerChargeId
        );

        $this->assertSame(
            'open',
            $result->status
        );

        $this->assertSame(
            'https://boleto.test/123',
            $result->checkoutUrl
        );

        $this->assertSame(
            '000201010212TESTE',
            $result->pixCopyPaste
        );

        Http::assertSentCount(2);
    }

    public function test_payload_uses_cnpj_address_and_integer_cents(): void
    {
        Http::fake([
            'https://cobrancas-h.api.efipay.com.br/v1/authorize'
                => Http::response([
                    'access_token' => 'token-test',
                ]),

            'https://cobrancas-h.api.efipay.com.br/v1/charge/one-step'
                => Http::response([
                    'code' => 200,
                    'data' => [
                        'charge_id' => 99,
                        'status' => 'waiting',
                    ],
                ]),
        ]);

        app(EfiPaymentProvider::class)
            ->createCharge(
                $this->request()
            );

        Http::assertSent(
            function (Request $request): bool {
                if (
                    $request->url()
                    !==
                    'https://cobrancas-h.api.efipay.com.br'
                    .'/v1/charge/one-step'
                ) {
                    return false;
                }

                return $request[
                    'items'
                ][0]['value'] === 85000
                    && $request[
                        'items'
                    ][0]['amount'] === 1
                    && $request[
                        'payment'
                    ][
                        'banking_billet'
                    ][
                        'customer'
                    ][
                        'juridical_person'
                    ]['cnpj']
                    === '12345678000199'
                    && $request[
                        'payment'
                    ][
                        'banking_billet'
                    ][
                        'customer'
                    ][
                        'address'
                    ]['zipcode']
                    === '01001000';
            }
        );
    }

    public function test_unpaid_remains_open_and_expired_is_overdue(): void
    {
        Http::fake([
            'https://cobrancas-h.api.efipay.com.br/v1/authorize'
                => Http::response([
                    'access_token' => 'token-test',
                ]),

            'https://cobrancas-h.api.efipay.com.br/v1/charge/123'
                => Http::response([
                    'code' => 200,
                    'data' => [
                        'charge_id' => 123,
                        'status' => 'unpaid',
                    ],
                ]),

            'https://cobrancas-h.api.efipay.com.br/v1/charge/456'
                => Http::response([
                    'code' => 200,
                    'data' => [
                        'charge_id' => 456,
                        'status' => 'expired',
                    ],
                ]),
        ]);

        $provider = app(
            EfiPaymentProvider::class
        );

        $unpaid = $provider->findCharge('123');
        $expired = $provider->findCharge('456');

        $this->assertSame(
            'open',
            $unpaid->status
        );

        $this->assertSame(
            'overdue',
            $expired->status
        );
    }

    public function test_production_is_blocked_without_live_flag(): void
    {
        config()->set(
            'finance_fiscal.providers.efi.environment',
            'production'
        );

        Http::fake();

        $this->expectException(
            LogicException::class
        );

        app(EfiPaymentProvider::class)
            ->createCharge(
                $this->request()
            );
    }

    public function test_missing_credentials_fail_before_http(): void
    {
        config()->set(
            'finance_fiscal.providers.efi.client_secret',
            null
        );

        Http::fake();

        $this->expectException(
            LogicException::class
        );

        app(EfiPaymentProvider::class)
            ->createCharge(
                $this->request()
            );
    }

    private function request(): PaymentChargeRequest
    {
        return new PaymentChargeRequest(
            invoicePublicId:
                '01TESTINVOICE0000000000000',

            idempotencyKey:
                'invoice:test:provider:efi',

            method:
                'boleto_pix',

            amount:
                '850.00',

            currency:
                'BRL',

            dueOn:
                '2026-08-20',

            payerName:
                'Empresa XYZ LTDA',

            payerDocument:
                '12.345.678/0001-99',

            payerEmail:
                'financeiro@cliente.test',

            payerPhone:
                '(11) 3333-4444',

            payerPostalCode:
                '01001-000',

            payerStreet:
                'Praça da Sé',

            payerAddressNumber:
                '100',

            payerAddressComplement:
                'Sala 1',

            payerDistrict:
                'Sé',

            payerCity:
                'São Paulo',

            payerState:
                'SP',

            payerCountry:
                'BR',
        );
    }
}
