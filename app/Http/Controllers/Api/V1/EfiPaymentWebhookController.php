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
                    'notification' =>
                        $postedToken,
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

        $receipt = PaymentWebhookReceipt::query()
                ->firstOrCreate([
                    'provider' => 'efi',
                    'token_hash' => hash('sha256', $token),
                ], [
                    'token_encrypted' =>
                        Crypt::encryptString(
                            $token
                        ),

                    'status' =>
                        PaymentWebhookReceipt::
                            STATUS_RECEIVED,

                    'received_at' => now(),
                ]);

        if ($receipt->wasRecentlyCreated) {
            $audit->record(
                module: 'finance',
                action: 'payment_webhook.received',
                entityType: 'payment_webhook_receipt',
                entityId: $receipt->id,
                metadata: ['provider' => 'efi'],
            );

            ProcessEfiPaymentWebhook::dispatch($receipt->id);
        } else {
            $audit->record(
                module: 'finance',
                action: 'payment_webhook.duplicate',
                entityType: 'payment_webhook_receipt',
                entityId: $receipt->id,
                metadata: ['provider' => 'efi'],
            );
        }

        /*
         * Não consulta a Efí dentro do request.
         * A confirmação real ocorrerá no Job.
         */
        return response()->json(
            [
                'accepted' => true,
                'duplicate' => ! $receipt->wasRecentlyCreated,
            ],
            200
        );
    }
}
