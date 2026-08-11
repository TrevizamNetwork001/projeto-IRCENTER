<?php

namespace Tests\Feature;

use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Finance\Data\PaymentChargeRequest;
use App\Modules\Finance\Infrastructure\EfiPaymentProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
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

    public function test_real_success_shape_maps_supported_artifacts(): void
    {
        $fixture = json_decode(
            file_get_contents(
                base_path(
                    'tests/Fixtures/efi/'
                    .'charge-one-step-success.json'
                )
            ),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        Http::fake([
            'https://cobrancas-h.api.efipay.com.br/v1/authorize'
                => Http::response([
                    'access_token' => 'token-test',
                ]),
            'https://cobrancas-h.api.efipay.com.br/v1/charge/one-step'
                => Http::response($fixture),
        ]);

        $result = app(EfiPaymentProvider::class)
            ->createCharge($this->request(amount: '59.90'));

        $this->assertSame('45000789', $result->providerChargeId);
        $this->assertSame('open', $result->status);
        $this->assertSame(
            'https://payments.example.test/charge',
            $result->checkoutUrl
        );
        $this->assertSame(
            'https://payments.example.test/billet',
            $result->billetUrl
        );
        $this->assertSame(
            'https://payments.example.test/billet.pdf?sandbox=true',
            $result->billetPdfUrl
        );
        $this->assertSame(
            '00190000090286000000600000000000000000000000',
            $result->barcode
        );
        $this->assertSame(
            '000201010212SANITIZED-PIX-COPY-PASTE',
            $result->pixCopyPaste
        );
        $this->assertSame(5990, $result->amountCents);
        $this->assertSame('2026-08-18', $result->dueOn);

        /*
         * qrcode_image é deliberadamente ignorado:
         * não persistimos SVG/base64 na Charge.
         */
        $this->assertObjectNotHasProperty(
            'pixQrCodeImage',
            $result
        );
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
                        'metadata'
                    ]['custom_id']
                    === 'invoice_test_provider_efi'
                    && $request[
                        'payment'
                    ][
                        'banking_billet'
                    ][
                        'customer'
                    ][
                        'juridical_person'
                    ]['cnpj']
                    === '12345678000195'
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

    #[DataProvider('validBrazilianPhones')]
    public function test_payload_normalizes_brazilian_phone(
        string $input,
        string $expected,
    ): void {
        $this->fakeSuccessfulCharge();

        app(EfiPaymentProvider::class)
            ->createCharge(
                $this->request($input)
            );

        Http::assertSent(
            fn (Request $request): bool =>
                $request->url() ===
                    'https://cobrancas-h.api.efipay.com.br'
                    .'/v1/charge/one-step'
                && $request[
                    'payment'
                ][
                    'banking_billet'
                ][
                    'customer'
                ]['phone_number'] === $expected
        );
    }

    public function test_invalid_phone_length_is_rejected_before_http(): void
    {
        Http::fake();

        try {
            app(EfiPaymentProvider::class)
                ->createCharge(
                    $this->request('+1 202 555 01234')
                );

            $this->fail(
                'Telefone internacional inválido deveria falhar.'
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Telefone do pagador é inválido.',
                $exception->getMessage()
            );
        }

        Http::assertNothingSent();
    }

    #[DataProvider('validBrazilianDocuments')]
    public function test_valid_document_is_normalized_and_sent(
        string $input,
        string $field,
        string $expected,
    ): void {
        $this->fakeSuccessfulCharge();

        app(EfiPaymentProvider::class)->createCharge(
            $this->request(payerDocument: $input)
        );

        Http::assertSent(
            fn (Request $request): bool =>
                $request->url() ===
                    'https://cobrancas-h.api.efipay.com.br'
                    .'/v1/charge/one-step'
                && data_get(
                    $request->data(),
                    'payment.banking_billet.customer.'.$field
                ) === $expected
        );
    }

    #[DataProvider('invalidBrazilianDocuments')]
    public function test_invalid_document_checksum_is_rejected_before_http(
        string $document,
    ): void {
        Http::fake();

        try {
            app(EfiPaymentProvider::class)->preflightCharge(
                $this->request(payerDocument: $document)
            );
            $this->fail('Documento inválido deveria falhar.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'CPF/CNPJ do pagador é inválido.',
                $exception->getMessage()
            );
        }

        Http::assertNothingSent();
    }

    public function test_invalid_email_is_rejected_before_http(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(EfiPaymentProvider::class)->preflightCharge(
                $this->request(payerEmail: 'email-invalido')
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    #[DataProvider('invalidRequiredAddressFields')]
    public function test_missing_required_address_is_rejected_before_http(
        string $field,
    ): void {
        Http::fake();

        $arguments = [$field => '   '];

        $this->expectException(InvalidArgumentException::class);

        try {
            app(EfiPaymentProvider::class)->preflightCharge(
                $this->request(...$arguments)
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_non_future_due_date_is_rejected_before_http(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(EfiPaymentProvider::class)->preflightCharge(
                $this->request(dueOn: now()->toDateString())
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    #[DataProvider('moneyValues')]
    public function test_payload_uses_exact_integer_cents(
        string $amount,
        int $expected,
    ): void {
        $this->fakeSuccessfulCharge();

        app(EfiPaymentProvider::class)->createCharge(
            $this->request(amount: $amount)
        );

        Http::assertSent(
            fn (Request $request): bool =>
                $request->url() === 'https://cobrancas-h.api.efipay.com.br/v1/charge/one-step'
                && $request['items'][0]['value'] === $expected
        );
    }

    public function test_preflight_rejects_unsupported_method_without_oauth(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(EfiPaymentProvider::class)->preflightCharge(
                $this->request(method: 'pix')
            );
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_unsafe_remote_link_is_not_exposed(): void
    {
        Http::fake([
            'https://cobrancas-h.api.efipay.com.br/v1/authorize' =>
                Http::response(['access_token' => 'token-test']),
            'https://cobrancas-h.api.efipay.com.br/v1/charge/one-step' =>
                Http::response(['data' => [
                    'charge_id' => 7,
                    'status' => 'waiting',
                    'link' => 'javascript:alert(1)',
                    'billet_link' => 'file:///tmp/billet',
                    'pdf' => [
                        'charge' => 'data:text/html,unsafe',
                    ],
                ]]),
        ]);

        $result = app(EfiPaymentProvider::class)->createCharge($this->request());

        $this->assertNull($result->checkoutUrl);
        $this->assertNull($result->billetUrl);
        $this->assertNull($result->billetPdfUrl);
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

    public function test_find_charge_by_correlation_fetches_exact_match(): void
    {
        Http::fake([
            'https://cobrancas-h.api.efipay.com.br/v1/authorize'
                => Http::response([
                    'access_token' =>
                        'token-test',
                ]),

            'https://cobrancas-h.api.efipay.com.br/v1/charges*'
                => Http::response([
                    'code' => 200,
                    'data' => [
                        [
                            'id' => 100,
                            'custom_id' =>
                                'another-correlation',
                        ],
                        [
                            'id' => 321,
                            'custom_id' =>
                                'invoice_test_provider_efi',
                        ],
                    ],
                ]),

            'https://cobrancas-h.api.efipay.com.br/v1/charge/321'
                => Http::response([
                    'code' => 200,
                    'data' => [
                        'charge_id' => 321,
                        'status' => 'waiting',
                        'link' =>
                            'https://boleto.test/321',
                        'pix' => [
                            'qrcode' =>
                                '000201TESTE321',
                        ],
                    ],
                ]),
        ]);

        $result = app(
            EfiPaymentProvider::class
        )->findChargeByCorrelation(
            'invoice:test:provider:efi',
            '2026-08-07',
            '2026-08-09',
        );

        $this->assertNotNull(
            $result
        );

        $this->assertSame(
            '321',
            $result->providerChargeId
        );

        $this->assertSame(
            'open',
            $result->status
        );

        $this->assertSame(
            'https://boleto.test/321',
            $result->checkoutUrl
        );

        $this->assertSame(
            '000201TESTE321',
            $result->pixCopyPaste
        );

        Http::assertSent(
            function (
                Request $request
            ): bool {
                if (
                    $request->method() !== 'GET'
                    || ! str_starts_with(
                        $request->url(),
                        'https://cobrancas-h.api.'
                        .'efipay.com.br/v1/charges'
                    )
                ) {
                    return false;
                }

                parse_str(
                    (string) parse_url(
                        $request->url(),
                        PHP_URL_QUERY
                    ),
                    $query
                );

                return (
                    $query['charge_type']
                    ?? null
                ) === 'billet'
                    && (
                        $query['custom_id']
                        ?? null
                    ) ===
                        'invoice_test_provider_efi'
                    && (
                        $query['begin_date']
                        ?? null
                    ) === '2026-08-07'
                    && (
                        $query['end_date']
                        ?? null
                    ) === '2026-08-09';
            }
        );

        /*
         * OAuth + listagem + detalhe.
         * O mesmo bearer é reutilizado.
         */
        Http::assertSentCount(3);
    }

    public function test_find_charge_by_correlation_returns_null_when_missing(): void
    {
        Http::fake([
            'https://cobrancas-h.api.efipay.com.br/v1/authorize'
                => Http::response([
                    'access_token' =>
                        'token-test',
                ]),

            'https://cobrancas-h.api.efipay.com.br/v1/charges*'
                => Http::response([
                    'code' => 200,
                    'data' => [
                        [
                            'id' => 999,
                            'custom_id' =>
                                'invoice:test:provider:efi',
                        ],
                    ],
                ]),
        ]);

        $result = app(
            EfiPaymentProvider::class
        )->findChargeByCorrelation(
            'invoice:test:provider:efi',
            '2026-08-07',
            '2026-08-09',
        );

        $this->assertNull(
            $result
        );

        Http::assertSentCount(2);
    }

    public function test_find_charge_by_correlation_blocks_ambiguous_matches(): void
    {
        Http::fake([
            'https://cobrancas-h.api.efipay.com.br/v1/authorize'
                => Http::response([
                    'access_token' =>
                        'token-test',
                ]),

            'https://cobrancas-h.api.efipay.com.br/v1/charges*'
                => Http::response([
                    'code' => 200,
                    'data' => [
                        [
                            'id' => 321,
                            'custom_id' =>
                                'invoice_test_provider_efi',
                        ],
                        [
                            'id' => 322,
                            'custom_id' =>
                                'invoice_test_provider_efi',
                        ],
                    ],
                ]),
        ]);

        try {
            app(
                EfiPaymentProvider::class
            )->findChargeByCorrelation(
                'invoice:test:provider:efi',
                '2026-08-07',
                '2026-08-09',
            );

            $this->fail(
                'Reconciliação ambígua deveria falhar.'
            );
        } catch (
            RuntimeException $exception
        ) {
            $this->assertStringContainsString(
                'ambígua',
                $exception->getMessage()
            );
        }

        /*
         * Não consulta detalhe nem faz qualquer POST
         * de cobrança em caso ambíguo.
         */
        Http::assertSentCount(2);
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

    public static function validBrazilianPhones(): array
    {
        return [
            'celular com DDI e formatação' => [
                '+5511986065675',
                '11986065675',
            ],
            'celular com DDI sem formatação' => [
                '5511986065675',
                '11986065675',
            ],
            'celular nacional' => [
                '11986065675',
                '11986065675',
            ],
            'telefone fixo nacional' => [
                '1133334444',
                '1133334444',
            ],
        ];
    }

    public static function moneyValues(): array
    {
        return [
            'um real' => ['1.00', 100],
            'cinquenta e nove e noventa' => ['59.90', 5990],
            'cem e um centavo' => ['100.01', 10001],
        ];
    }

    public static function validBrazilianDocuments(): array
    {
        return [
            'CPF formatado' => ['942.715.646-56', 'cpf', '94271564656'],
            'CPF somente dígitos' => ['94271564656', 'cpf', '94271564656'],
            'CNPJ formatado' => ['12.345.678/0001-95', 'juridical_person.cnpj', '12345678000195'],
            'CNPJ somente dígitos' => ['12345678000195', 'juridical_person.cnpj', '12345678000195'],
        ];
    }

    public static function invalidBrazilianDocuments(): array
    {
        return [
            'CPF checksum inválido' => ['94271564657'],
            'CPF repetido' => ['11111111111'],
            'CNPJ checksum inválido' => ['12345678000199'],
            'CNPJ repetido' => ['00000000000000'],
        ];
    }

    public static function invalidRequiredAddressFields(): array
    {
        return [
            'logradouro' => ['payerStreet'],
            'número' => ['payerAddressNumber'],
            'bairro' => ['payerDistrict'],
            'cidade' => ['payerCity'],
            'UF' => ['payerState'],
        ];
    }

    private function fakeSuccessfulCharge(): void
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
    }

    private function request(
        string $payerPhone = '(11) 3333-4444',
        string $amount = '850.00',
        string $method = 'boleto_pix',
        string $payerDocument = '12.345.678/0001-95',
        string $payerEmail = 'financeiro@cliente.test',
        string $payerStreet = 'Praça da Sé',
        string $payerAddressNumber = '100',
        string $payerDistrict = 'Sé',
        string $payerCity = 'São Paulo',
        string $payerState = 'SP',
        string $dueOn = '2026-08-20',
    ): PaymentChargeRequest
    {
        return new PaymentChargeRequest(
            invoicePublicId:
                '01TESTINVOICE0000000000000',

            idempotencyKey:
                'invoice:test:provider:efi',

            method:
                $method,

            amount:
                $amount,

            currency:
                'BRL',

            dueOn:
                $dueOn,

            payerName:
                'Empresa XYZ LTDA',

            payerDocument:
                $payerDocument,

            payerEmail:
                $payerEmail,

            payerPhone:
                $payerPhone,

            payerPostalCode:
                '01001-000',

            payerStreet:
                $payerStreet,

            payerAddressNumber:
                $payerAddressNumber,

            payerAddressComplement:
                'Sala 1',

            payerDistrict:
                $payerDistrict,

            payerCity:
                $payerCity,

            payerState:
                $payerState,

            payerCountry:
                'BR',
        );
    }
}
