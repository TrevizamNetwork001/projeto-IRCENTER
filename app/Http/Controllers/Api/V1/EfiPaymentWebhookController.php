<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessEfiPaymentWebhook;
use App\Modules\Finance\Infrastructure\EfiPaymentProvider;
use App\Modules\Finance\Models\PaymentWebhookReceipt;
use App\Modules\Shared\Services\DomainAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EfiPaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        EfiPaymentProvider $provider,
        DomainAudit $audit,
    ): JsonResponse {
        if (
            ! config(
                'finance_fiscal.finance.'
                .'payment_webhooks_enabled',
                false
            )
        ) {
            abort(404);
        }

        $rawBody = $request->getContent();

        $contentType = strtolower((string) $request->header('Content-Type'));

        if (
            $contentType !== ''
            && ! str_starts_with($contentType, 'application/x-www-form-urlencoded')
            && ! str_starts_with($contentType, 'application/json')
            && ! str_starts_with($contentType, 'multipart/form-data')
        ) {
            return response()->json(['message' => 'Content-Type não suportado.'], 415);
        }

        if (
            str_starts_with($contentType, 'application/json')
            && json_decode($rawBody, true) === null
            && json_last_error() !== JSON_ERROR_NONE
        ) {
            return response()->json(['message' => 'JSON inválido.'], 422);
        }

        $contentLength = (int) (
            $request->header('Content-Length') ?? 0
        );

        if (
            strlen($rawBody) > 4096
            || $contentLength > 4096
        ) {
            return response()->json(
                ['message' => 'Payload muito grande.'],
                413
            );
        }

        /*
         * A Efí documenta o callback como parâmetro
         * POST notification ($_POST['notification']).
         *
         * Em HTTP real também podemos receber o body
         * application/x-www-form-urlencoded.
         */
        $postedToken = $request->input(
            'notification'
        );

        if (is_string($postedToken)) {
            $validationBody = http_build_query(
                [
                    'notification' => $postedToken,
                ],
                '',
                '&',
                PHP_QUERY_RFC3986
            );
        } else {
            $validationBody = $rawBody;
        }

        if (
            ! $provider->validateWebhookRequest(
                $validationBody,
                $request->headers->all()
            )
        ) {
            return response()->json(
                ['message' => 'Notificação inválida.'],
                422
            );
        }

        $parsed = $provider->parseWebhook(
            $validationBody,
            $request->headers->all()
        );

        $token = $parsed['notification'];

        $tokenHash = hash('sha256', $token);
        $receivedAt = now();

        $receipt = DB::connection('finance_fiscal')->transaction(
            function () use ($token, $tokenHash, $receivedAt): PaymentWebhookReceipt {
                DB::connection('finance_fiscal')
                    ->table('payment_webhook_receipts')
                    ->insertOrIgnore([
                        'public_id' => (string) Str::ulid(),
                        'provider' => 'efi',
                        'token_hash' => $tokenHash,
                        'token_encrypted' => Crypt::encryptString($token),
                        'status' => PaymentWebhookReceipt::STATUS_RECEIVED,
                        'attempt_count' => 0,
                        'received_at' => $receivedAt,
                        'last_received_at' => $receivedAt,
                        'receive_count' => 0,
                        'created_at' => $receivedAt,
                        'updated_at' => $receivedAt,
                    ]);

                $receipt = PaymentWebhookReceipt::query()
                    ->where('provider', 'efi')
                    ->where('token_hash', $tokenHash)
                    ->lockForUpdate()
                    ->firstOrFail();

                $receipt->increment('receive_count', 1, [
                    'last_received_at' => $receivedAt,
                    'status' => PaymentWebhookReceipt::STATUS_RECEIVED,
                ]);

                return $receipt->refresh();
            }
        );

        $audit->record(
            module: 'finance',
            action: 'payment_webhook.received',
            entityType: 'payment_webhook_receipt',
            entityId: $receipt->id,
            metadata: [
                'provider' => 'efi',
                'receive_count' => $receipt->receive_count,
            ],
            correlationId: is_string(
                $request->attributes->get('request_id')
            ) ? $request->attributes->get('request_id') : null,
        );

        /* O mesmo token pode revelar eventos novos em cada entrega. */
        ProcessEfiPaymentWebhook::dispatch($receipt->id);

        /*
         * Não consulta a Efí dentro do request.
         * A confirmação real ocorrerá no Job.
         */
        return response()->json(
            [
                'accepted' => true,
                'duplicate_token' => $receipt->receive_count > 1,
                'processing_scheduled' => true,
            ],
            200
        );
    }
}
