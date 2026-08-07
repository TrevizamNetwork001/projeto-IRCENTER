<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessEfiPaymentWebhook;
use App\Modules\Finance\Infrastructure\EfiPaymentProvider;
use App\Modules\Finance\Models\PaymentWebhookReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

final class EfiPaymentWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        EfiPaymentProvider $provider,
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

        $receipt =
            PaymentWebhookReceipt::query()
                ->create([
                    'provider' => 'efi',

                    'token_hash' =>
                        hash('sha256', $token),

                    'token_encrypted' =>
                        Crypt::encryptString(
                            $token
                        ),

                    'status' =>
                        PaymentWebhookReceipt::
                            STATUS_RECEIVED,

                    'received_at' => now(),
                ]);

        ProcessEfiPaymentWebhook::dispatch(
            $receipt->id
        );

        /*
         * Não consulta a Efí dentro do request.
         * A confirmação real ocorrerá no Job.
         */
        return response()->json(
            ['accepted' => true],
            200
        );
    }
}
