<?php

namespace App\Modules\Finance\Infrastructure;

use App\Modules\Finance\Contracts\CorrelatablePaymentProvider;
use App\Modules\Finance\Contracts\PaymentProvider;
use App\Modules\Finance\Contracts\PreflightsPaymentCharges;
use App\Modules\Finance\Data\EfiNotificationEvent;
use App\Modules\Finance\Data\PaymentChargeRequest;
use App\Modules\Finance\Data\PaymentChargeResult;
use App\Modules\Finance\Exceptions\EfiNotificationNotFound;
use App\Modules\Finance\Support\Decimal;
use App\Modules\Finance\Support\EfiCustomId;
use App\Modules\Finance\Support\EfiStatusMapper;
use App\Support\BusinessClock;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

final class EfiPaymentProvider implements CorrelatablePaymentProvider, PaymentProvider, PreflightsPaymentCharges
{
    public function __construct(
        private readonly BusinessClock $clock,
    ) {}

    public function key(): string
    {
        return 'efi';
    }

    public function capabilities(): array
    {
        return [
            'boleto',
            'boleto_pix',
            'webhook',
            'cancel',
        ];
    }

    public function isLive(): bool
    {
        return $this->environment()
            === 'production';
    }

    public function createCharge(
        PaymentChargeRequest $request,
    ): PaymentChargeResult {
        $payload = $this->chargePayload($request);

        $this->assertOperationAllowed();

        $response = $this->authorizedRequest()
            ->post($this->baseUrl().'/v1/charge/one-step', $payload);

        $response->throw();
        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('Resposta inválida da Efí.');
        }

        return $this->resultFromData($data);
    }

    public function preflightCharge(PaymentChargeRequest $request): void
    {
        $this->chargePayload($request);
    }

    /** @return array<string, mixed> */
    private function chargePayload(PaymentChargeRequest $request): array
    {

        if (
            ! in_array(
                $request->method,
                ['boleto', 'boleto_pix'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Método não suportado pela Efí Cobranças.'
            );
        }

        if ($request->currency !== 'BRL') {
            throw new InvalidArgumentException('A moeda da cobrança Efí deve ser BRL.');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->dueOn) !== 1) {
            throw new InvalidArgumentException('Vencimento da cobrança Efí é inválido.');
        }

        if (
            $request->dueOn <= $this->clock->today()->toDateString()
        ) {
            throw new InvalidArgumentException(
                'Vencimento da cobrança Efí deve ser futuro.'
            );
        }

        $valueCents = Decimal::moneyToCents($request->amount);

        if ($valueCents < 1) {
            throw new InvalidArgumentException('Valor da cobrança Efí deve ser positivo.');
        }

        $payload = [
            'items' => [
                [
                    'name' => 'Fatura '.$request->invoicePublicId,

                    'value' => $valueCents,

                    'amount' => 1,
                ],
            ],

            'metadata' => [
                'custom_id' => EfiCustomId::fromCorrelationId(
                    $request->idempotencyKey
                ),
            ],

            'payment' => [
                'banking_billet' => [
                'customer' => $this->customer($request),

                'expire_at' => $request->dueOn,
                ],
            ],
        ];

        $notificationUrl = trim(
            (string) config(
                'finance_fiscal.providers.'
                .'efi.notification_url'
            )
        );

        if ($notificationUrl !== '') {
            if (
                ! filter_var(
                    $notificationUrl,
                    FILTER_VALIDATE_URL
                )
                || ! str_starts_with(
                    strtolower($notificationUrl),
                    'https://'
                )
            ) {
                throw new LogicException(
                    'EFI_NOTIFICATION_URL deve usar HTTPS.'
                );
            }

            $payload['metadata']['notification_url']
                = $notificationUrl;
        }

        return $payload;
    }

    public function findCharge(
        string $providerChargeId,
    ): ?PaymentChargeResult {
        $this->assertOperationAllowed();

        return $this->findChargeWithRequest(
            $this->authorizedRequest(),
            $providerChargeId,
        );
    }

    public function findChargeByCorrelation(
        string $correlationId,
        string $beginDate,
        string $endDate,
    ): ?PaymentChargeResult {
        $remoteCustomId =
            EfiCustomId::fromCorrelationId(
                $correlationId
            );

        $this->assertOperationAllowed();

        $correlationId = trim(
            $correlationId
        );

        if (
            $correlationId === ''
            || strlen($correlationId) > 180
        ) {
            throw new InvalidArgumentException(
                'Correlação de cobrança Efí inválida.'
            );
        }

        $this->assertLookupDateRange(
            $beginDate,
            $endDate,
        );

        /*
         * Um único OAuth é reutilizado tanto para
         * a listagem quanto para o detalhe.
         */
        $http = $this->authorizedRequest();

        $response = $http->get(
            $this->baseUrl()
                .'/v1/charges',
            [
                'charge_type' => 'billet',
                'custom_id' => $remoteCustomId,
                'begin_date' => $beginDate,
                'end_date' => $endDate,
                'limit' => 100,
                'page' => 1,
            ],
        );

        $response->throw();

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException(
                'Listagem de cobranças Efí inválida.'
            );
        }

        /*
         * Mesmo enviando custom_id como filtro,
         * nunca confiamos apenas no filtro remoto.
         */
        $matches = array_values(
            array_filter(
                $data,
                static function (
                    mixed $item
                ) use (
                    $remoteCustomId
                ): bool {
                    return is_array($item)
                        && isset(
                            $item['custom_id']
                        )
                        && (string) $item[
                            'custom_id'
                        ] === $remoteCustomId;
                }
            )
        );

        if ($matches === []) {
            return null;
        }

        if (count($matches) !== 1) {
            throw new RuntimeException(
                'Reconciliação Efí ambígua: '
                .'mais de uma cobrança encontrada.'
            );
        }

        $providerChargeId =
            $matches[0]['id']
            ?? null;

        if (
            ! is_int($providerChargeId)
            && ! is_string($providerChargeId)
        ) {
            throw new RuntimeException(
                'ID da cobrança Efí ausente '
                .'na reconciliação.'
            );
        }

        $providerChargeId = trim(
            (string) $providerChargeId
        );

        if ($providerChargeId === '') {
            throw new RuntimeException(
                'ID da cobrança Efí inválido '
                .'na reconciliação.'
            );
        }

        return $this->findChargeWithRequest(
            $http,
            $providerChargeId,
        );
    }

    public function cancelCharge(
        string $providerChargeId,
        string $idempotencyKey,
    ): PaymentChargeResult {
        $this->assertOperationAllowed();

        $response = $this->authorizedRequest()
            ->put(
                $this->baseUrl()
                    .'/v1/charge/'
                    .rawurlencode($providerChargeId)
                    .'/cancel'
            );

        $response->throw();

        return new PaymentChargeResult(
            providerChargeId: $providerChargeId,
            status: 'canceled',
        );
    }

    /** @return array{http_status: int, code: int|string|null, message: string|null} */
    public function updateNotificationMetadata(
        string $providerChargeId,
        string $notificationUrl,
        string $customId,
    ): array {
        $providerChargeId = trim($providerChargeId);
        $notificationUrl = trim($notificationUrl);
        $customId = trim($customId);

        if (preg_match('/^\d{1,30}$/D', $providerChargeId) !== 1) {
            throw new InvalidArgumentException('ID da cobrança Efí inválido.');
        }

        if (
            ! filter_var($notificationUrl, FILTER_VALIDATE_URL)
            || ! str_starts_with(strtolower($notificationUrl), 'https://')
            || strlen($notificationUrl) > 255
        ) {
            throw new InvalidArgumentException('notification_url Efí deve usar HTTPS.');
        }

        if (
            $customId === ''
            || strlen($customId) > 255
            || str_contains($customId, ':')
            || preg_match('/^[A-Za-z0-9_-]+$/D', $customId) !== 1
        ) {
            throw new InvalidArgumentException('custom_id Efí inválido.');
        }

        $this->assertOperationAllowed();

        $response = $this->authorizedRequest()->put(
            $this->baseUrl()
                .'/v1/charge/'
                .rawurlencode($providerChargeId)
                .'/metadata',
            [
                'notification_url' => $notificationUrl,
                'custom_id' => $customId,
            ],
        );

        $response->throw();

        $message = $response->json('data');

        return [
            'http_status' => $response->status(),
            'code' => $response->json('code'),
            'message' => is_string($message) ? mb_substr($message, 0, 500) : null,
        ];
    }

    /**
     * @return list<EfiNotificationEvent>
     */
    public function fetchNotificationEvents(
        string $token,
    ): array {
        $this->assertOperationAllowed();

        if (
            preg_match(
                '/^[A-Za-z0-9-]{10,120}$/',
                $token
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Token de notificação Efí inválido.'
            );
        }

        $response = $this->authorizedRequest()
            ->get(
                $this->baseUrl()
                    .'/v1/notification/'
                    .rawurlencode($token)
            );

        if (
            $response->notFound()
            || (
                $response->status() === 500
                && $response->json('code') === 3500010
                && $response->json('error_description.property') === 'notification'
            )
        ) {
            throw new EfiNotificationNotFound;
        }

        $response->throw();

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException(
                'Histórico de notificação Efí inválido.'
            );
        }

        $events = [];

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $eventId = $item['id'] ?? null;

            $chargeId = data_get(
                $item,
                'identifiers.charge_id'
            );

            $type = (string) (
                $item['type'] ?? ''
            );

            $current = data_get(
                $item,
                'status.current'
            );

            $previous = data_get(
                $item,
                'status.previous'
            );

            if (
                (! is_int($eventId)
                    && ! is_string($eventId))
                || (! is_int($chargeId)
                    && ! is_string($chargeId))
                || $type === ''
                || ! is_string($current)
            ) {
                continue;
            }

            $value = $item['value'] ?? null;

            $valueCents = (
                is_int($value)
                && $value >= 0
            ) ? $value : null;

            $received = $item[
                'received_by_bank_at'
            ] ?? null;

            $created = $item[
                'created_at'
            ] ?? null;

            $events[] =
                new EfiNotificationEvent(
                    eventId: (string) $eventId,

                    chargeId: (string) $chargeId,

                    type: $type,

                    currentStatus: $current,

                    previousStatus: is_string($previous)
                            ? $previous
                            : null,

                    normalizedStatus: EfiStatusMapper::toLocal($current),

                    valueCents: $valueCents,

                    receivedByBankAt: is_string($received)
                            ? $received
                            : null,

                    providerCreatedAtRaw: is_string($created)
                            ? $created
                            : null,

                    payload: $item,
                );
        }

        usort(
            $events,
            static function (
                EfiNotificationEvent $left,
                EfiNotificationEvent $right
            ): int {
                $createdComparison = strcmp(
                    $left->providerCreatedAtRaw ?? '',
                    $right->providerCreatedAtRaw ?? ''
                );

                if ($createdComparison !== 0) {
                    return $createdComparison;
                }

                if (ctype_digit($left->eventId) && ctype_digit($right->eventId)) {
                    return (int) $left->eventId <=> (int) $right->eventId;
                }

                return strcmp($left->eventId, $right->eventId);
            }
        );

        return $events;
    }

    public function validateWebhookRequest(
        string $rawBody,
        array $headers,
    ): bool {
        parse_str($rawBody, $payload);

        $token = $payload['notification']
            ?? null;

        return is_string($token)
            && preg_match(
                '/^[A-Za-z0-9-]{10,120}$/',
                $token
            ) === 1;
    }

    public function parseWebhook(
        string $rawBody,
        array $headers,
    ): array {
        parse_str($rawBody, $payload);

        $token = $payload['notification']
            ?? null;

        if (
            ! is_string($token)
            || ! $this->validateWebhookRequest(
                $rawBody,
                $headers
            )
        ) {
            throw new InvalidArgumentException(
                'Notificação Efí inválida.'
            );
        }

        return [
            'notification' => $token,
        ];
    }

    private function findChargeWithRequest(
        PendingRequest $http,
        string $providerChargeId,
    ): ?PaymentChargeResult {
        $response = $http->get(
            $this->baseUrl()
                .'/v1/charge/'
                .rawurlencode(
                    $providerChargeId
                )
        );

        if ($response->status() === 404) {
            return null;
        }

        $response->throw();

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException(
                'Resposta inválida da Efí.'
            );
        }

        return $this->resultFromData(
            $data
        );
    }

    private function assertLookupDateRange(
        string $beginDate,
        string $endDate,
    ): void {
        foreach (
            [
                'begin_date' => $beginDate,
                'end_date' => $endDate,
            ] as $field => $value
        ) {
            if (
                preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $value
                ) !== 1
            ) {
                throw new InvalidArgumentException(
                    $field.' Efí inválida.'
                );
            }

            [
                $year,
                $month,
                $day,
            ] = array_map(
                'intval',
                explode('-', $value)
            );

            if (
                ! checkdate(
                    $month,
                    $day,
                    $year
                )
            ) {
                throw new InvalidArgumentException(
                    $field.' Efí inválida.'
                );
            }
        }

        /*
         * YYYY-MM-DD pode ser comparado
         * lexicalmente depois da validação.
         */
        if ($beginDate > $endDate) {
            throw new InvalidArgumentException(
                'Período de reconciliação Efí '
                .'inválido.'
            );
        }
    }

    private function authorizedRequest(): PendingRequest
    {
        [$clientId, $clientSecret] =
            $this->credentials();

        $response = Http::acceptJson()
            ->asJson()
            ->withBasicAuth(
                $clientId,
                $clientSecret
            )
            ->connectTimeout(5)
            ->timeout(15)
            ->post(
                $this->baseUrl()
                    .'/v1/authorize',
                [
                    'grant_type' => 'client_credentials',
                ]
            );

        $response->throw();

        $token = $response->json(
            'access_token'
        );

        if (
            ! is_string($token)
            || trim($token) === ''
        ) {
            throw new RuntimeException(
                'Token OAuth Efí ausente.'
            );
        }

        return Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->connectTimeout(5)
            ->timeout(15);
    }

    private function customer(
        PaymentChargeRequest $request,
    ): array {
        $document = preg_replace(
            '/\D+/',
            '',
            (string) $request->payerDocument
        );

        $phone = $this->normalizeBrazilianPhone(
            $request->payerPhone
        );

        $zipcode = preg_replace(
            '/\D+/',
            '',
            (string) $request->payerPostalCode
        );

        $email = trim(
            (string) $request->payerEmail
        );

        $street = trim(
            (string) $request->payerStreet
        );

        $number = trim(
            (string) $request->payerAddressNumber
        );

        $district = trim(
            (string) $request->payerDistrict
        );

        $city = trim(
            (string) $request->payerCity
        );

        $state = strtoupper(
            trim(
                (string) $request->payerState
            )
        );

        if (! $this->isValidBrazilianDocument($document)) {
            throw new InvalidArgumentException(
                'CPF/CNPJ do pagador é inválido.'
            );
        }

        if (
            $email === ''
            || ! filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new InvalidArgumentException(
                'E-mail financeiro do pagador é obrigatório.'
            );
        }

        if (strlen($zipcode) !== 8) {
            throw new InvalidArgumentException(
                'CEP do pagador é inválido.'
            );
        }

        if (
            $street === ''
            || $number === ''
            || $district === ''
            || $city === ''
            || ! in_array(
                $state,
                [
                    'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF',
                    'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
                    'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS',
                    'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Endereço do pagador está incompleto.'
            );
        }

        $customer = [
            'email' => $email,
            'phone_number' => $phone,

            'address' => [
                'street' => $street,
                'number' => $number,
                'neighborhood' => $district,
                'zipcode' => $zipcode,
                'city' => $city,
                'complement' => trim(
                    (string)
                    $request
                        ->payerAddressComplement
                ),
                'state' => $state,
            ],
        ];

        if (strlen($document) === 11) {
            $name = trim(
                $request->payerName
            );

            if ($name === '') {
                throw new InvalidArgumentException(
                    'Nome do pagador é obrigatório.'
                );
            }

            $customer['name'] = $name;
            $customer['cpf'] = $document;
        } else {
            $name = trim(
                $request->payerName
            );

            if ($name === '') {
                throw new InvalidArgumentException(
                    'Razão social é obrigatória.'
                );
            }

            $customer['juridical_person'] = [
                'corporate_name' => $name,
                'cnpj' => $document,
            ];
        }

        return $customer;
    }

    private function isValidBrazilianDocument(
        string $document,
    ): bool {
        $length = strlen($document);

        if (! in_array($length, [11, 14], true)) {
            return false;
        }

        if (preg_match('/^(\d)\1+$/', $document) === 1) {
            return false;
        }

        return $length === 11
            ? $this->hasValidCpfChecksum($document)
            : $this->hasValidCnpjChecksum($document);
    }

    private function hasValidCpfChecksum(string $document): bool
    {
        for ($digit = 9; $digit < 11; $digit++) {
            $sum = 0;

            for ($position = 0; $position < $digit; $position++) {
                $sum += (int) $document[$position]
                    * (($digit + 1) - $position);
            }

            $expected = (10 * $sum) % 11;
            $expected = $expected === 10 ? 0 : $expected;

            if ((int) $document[$digit] !== $expected) {
                return false;
            }
        }

        return true;
    }

    private function hasValidCnpjChecksum(string $document): bool
    {
        $weights = [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        foreach ($weights as $index => $digitWeights) {
            $sum = 0;

            foreach ($digitWeights as $position => $weight) {
                $sum += (int) $document[$position] * $weight;
            }

            $remainder = $sum % 11;
            $expected = $remainder < 2 ? 0 : 11 - $remainder;

            if ((int) $document[12 + $index] !== $expected) {
                return false;
            }
        }

        return true;
    }

    private function normalizeBrazilianPhone(
        ?string $phone,
    ): string {
        $digits = preg_replace(
            '/\D+/',
            '',
            (string) $phone
        );

        if (
            in_array(
                strlen($digits),
                [12, 13],
                true
            )
            && str_starts_with(
                $digits,
                '55'
            )
        ) {
            $digits = substr($digits, 2);
        }

        if (
            ! in_array(
                strlen($digits),
                [10, 11],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Telefone do pagador é inválido.'
            );
        }

        return $digits;
    }

    private function resultFromData(
        array $data,
    ): PaymentChargeResult {
        /*
         * pix.qrcode_image é deliberadamente ignorado.
         * O SVG/base64 pode ser grande e o Pix copia-e-cola
         * já atende o fluxo operacional sem persistir blob.
         */
        $chargeId = $data['charge_id']
            ?? $data['id']
            ?? null;

        if (
            ! is_int($chargeId)
            && ! is_string($chargeId)
        ) {
            throw new RuntimeException(
                'charge_id Efí ausente.'
            );
        }

        if (trim((string) $chargeId) === '') {
            throw new RuntimeException('charge_id Efí inválido.');
        }

        $status = (string) (
            $data['status'] ?? ''
        );

        return new PaymentChargeResult(
            providerChargeId: (string) $chargeId,

            status: EfiStatusMapper::toLocal($status),

            checkoutUrl: $this->safeArtifactUrl(
                $data['link']
                ?? $data['billet_link']
                ?? data_get(
                    $data,
                    'payment.banking_billet.link'
                )
            ),

            pixCopyPaste: data_get(
                $data,
                'pix.qrcode'
            )
                ?? data_get(
                    $data,
                    'payment.banking_billet.'
                    .'pix.qrcode'
                ),

            billetUrl: $this->safeArtifactUrl(
                $data['billet_link']
                ?? data_get(
                    $data,
                    'payment.banking_billet.billet_link'
                )
            ),

            billetPdfUrl: $this->safeArtifactUrl(
                data_get(
                    $data,
                    'pdf.charge'
                )
                ?? data_get(
                    $data,
                    'payment.banking_billet.pdf.charge'
                )
            ),

            barcode: $this->safeBarcode(
                $data['barcode']
                ?? data_get(
                    $data,
                    'payment.banking_billet.barcode'
                )
            ),

            amountCents: $this->safeAmountCents(
                $data['total']
                ?? null
            ),

            dueOn: $this->safeDate(
                $data['expire_at']
                ?? data_get(
                    $data,
                    'payment.banking_billet.expire_at'
                )
            ),
        );
    }

    private function safeAmountCents(mixed $amount): ?int
    {
        if (is_int($amount) && $amount >= 0) {
            return $amount;
        }

        if (
            is_string($amount)
            && preg_match('/^\d+$/D', $amount) === 1
        ) {
            return (int) $amount;
        }

        return null;
    }

    private function safeDate(mixed $date): ?string
    {
        if (
            ! is_string($date)
            || preg_match(
                '/^\d{4}-\d{2}-\d{2}$/D',
                $date
            ) !== 1
        ) {
            return null;
        }

        return $date;
    }

    private function safeBarcode(mixed $barcode): ?string
    {
        if (! is_string($barcode)) {
            return null;
        }

        $barcode = trim($barcode);

        if (
            $barcode === ''
            || strlen($barcode) > 255
        ) {
            return null;
        }

        return $barcode;
    }

    private function safeArtifactUrl(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        return filter_var($url, FILTER_VALIDATE_URL)
            && str_starts_with(strtolower($url), 'https://')
                ? $url
                : null;
    }

    private function assertOperationAllowed(): void
    {
        if (
            $this->isLive()
            && ! config(
                'finance_fiscal.finance.'
                .'payment_live_enabled',
                false
            )
        ) {
            throw new LogicException(
                'Efí produção está bloqueada.'
            );
        }
    }

    private function credentials(): array
    {
        $clientId = trim(
            (string) config(
                'finance_fiscal.providers.'
                .'efi.client_id'
            )
        );

        $clientSecret = trim(
            (string) config(
                'finance_fiscal.providers.'
                .'efi.client_secret'
            )
        );

        if (
            $clientId === ''
            || $clientSecret === ''
        ) {
            throw new LogicException(
                'Credenciais Efí não configuradas.'
            );
        }

        return [
            $clientId,
            $clientSecret,
        ];
    }

    private function environment(): string
    {
        $environment = config(
            'finance_fiscal.providers.'
            .'efi.environment',
            'homologation'
        );

        return match ($environment) {
            'homologation',
            'production' => $environment,

            default => throw new LogicException(
                'Ambiente Efí inválido.'
            ),
        };
    }

    private function baseUrl(): string
    {
        return match ($this->environment()) {
            'homologation' => 'https://cobrancas-h.api.efipay.com.br',

            'production' => 'https://cobrancas.api.efipay.com.br',
        };
    }
}
