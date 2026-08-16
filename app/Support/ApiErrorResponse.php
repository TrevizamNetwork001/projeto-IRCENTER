<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiErrorResponse
{
    /**
     * @param  array<string, list<string>>|null  $details
     */
    public static function make(
        string $code,
        string $message,
        int $status,
        Request $request,
        ?array $details = null,
        array $headers = [],
    ): JsonResponse {
        $error = [
            'code' => $code,
            'message' => $message,
            'request_id' => (string) $request->attributes->get('request_id'),
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return new JsonResponse(
            [
                'error' => $error,
                // Compatibilidade temporária com o contrato anterior.
                'message' => $message,
            ],
            $status,
            $headers,
        );
    }
}
