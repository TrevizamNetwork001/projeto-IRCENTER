<?php

namespace App\Services\Irr;

use App\Models\IrrAsSet;
use App\Models\IrrMaintainer;
use App\Models\IrrObject;
use App\Models\IrrRoute;
use App\Models\IrrSubmission;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cliente da API de submissão de objetos do TC (bgp.net.br).
 *
 * Regras obrigatórias:
 * - a API SEMPRE responde HTTP 200 quando o JSON é válido — o sucesso
 *   real está em objects[].successful, nunca em response->successful();
 * - HTTP 400 com corpo text/plain indica JSON malformado na requisição;
 * - toda chamada gera um registro em irr_submissions (auditoria), com a
 *   senha mascarada antes de gravar o request;
 * - o status/last_published_at/last_error do objeto são sempre
 *   atualizados a partir do resultado real, nunca do status HTTP;
 * - timeout de 30s, com 1 retry só em erro de conexão — nunca em erro de
 *   objeto (não chamamos ->throw(), então um objects[].successful=false
 *   nunca dispara retry);
 * - toda publicação bem-sucedida (não delete) espelha o objeto no
 *   catálogo manual (irr_objects, source=TC) — ver syncIrrObjectCatalog().
 */
final class TcIrrClient
{
    private const ENDPOINT = 'https://bgp.net.br/v1/submit/';

    public function publish(IrrMaintainer $maintainer, IrrRoute|IrrAsSet $object, string $rpslText, string $operation): array
    {
        return $this->send($maintainer, $object, $rpslText, $operation, null);
    }

    public function delete(IrrMaintainer $maintainer, IrrRoute|IrrAsSet $object, string $rpslText, string $reason): array
    {
        return $this->send($maintainer, $object, $rpslText, IrrSubmission::OPERATION_DELETE, $reason);
    }

    private function send(
        IrrMaintainer $maintainer,
        IrrRoute|IrrAsSet $object,
        string $rpslText,
        string $operation,
        ?string $deleteReason,
    ): array {
        $body = [
            'objects' => [
                ['object_text' => $rpslText],
            ],
            'passwords' => [$maintainer->password],
        ];

        if ($deleteReason !== null) {
            $body['delete_reason'] = $deleteReason;
        }

        $maskedRequest = $body;
        $maskedRequest['passwords'] = ['***'];

        // retry(2, ...): o "times" do client HTTP do Laravel é o total de
        // tentativas (não o de retries adicionais) — 2 aqui dá exatamente
        // 1 retry após a tentativa inicial, como pede a regra de negócio.
        // $throw=false: um 400/500 é dado a interpretar (ex.: JSON
        // malformado), não uma exceção — só erro de conexão deve virar
        // exceção (e só esse dispara o retry, via $when abaixo).
        $pending = Http::timeout(30)->retry(
            2,
            500,
            static fn (Throwable $exception): bool => $exception instanceof ConnectionException,
            false,
        );

        try {
            $response = $operation === IrrSubmission::OPERATION_DELETE
                ? $pending->delete(self::ENDPOINT, $body)
                : $pending->post(self::ENDPOINT, $body);
        } catch (ConnectionException $exception) {
            return $this->recordFailure($object, $operation, $maskedRequest, null, $exception->getMessage());
        }

        return $this->recordResponse($maintainer, $object, $operation, $maskedRequest, $response, $rpslText);
    }

    /**
     * @param array<string, mixed> $maskedRequest
     */
    private function recordResponse(
        IrrMaintainer $maintainer,
        IrrRoute|IrrAsSet $object,
        string $operation,
        array $maskedRequest,
        Response $response,
        string $rpslText,
    ): array {
        $contentType = (string) $response->header('Content-Type');
        $decoded = $response->json();

        if (str_contains($contentType, 'text/plain') || ! is_array($decoded)) {
            $error = 'Resposta do TC não é JSON válido: '.mb_substr($response->body(), 0, 500);

            return $this->recordFailure($object, $operation, $maskedRequest, ['raw' => $response->body()], $error);
        }

        $objects = is_array($decoded['objects'] ?? null) ? $decoded['objects'] : [];
        $result = $objects[0] ?? null;
        $successful = is_array($result) && ($result['successful'] ?? false) === true;

        IrrSubmission::query()->create([
            'submittable_type' => $object::class,
            'submittable_id' => $object->getKey(),
            'operation' => $operation,
            'request_payload' => $maskedRequest,
            'response_payload' => $decoded,
            'successful' => $successful,
        ]);

        if ($successful) {
            $object->markPublished();

            // Só sincroniza o catálogo manual (irr_objects) numa
            // publicação — um delete bem-sucedido não deveria criar
            // nem reativar uma entrada ali.
            if ($operation !== IrrSubmission::OPERATION_DELETE) {
                $this->syncIrrObjectCatalog($maintainer, $object, is_array($result) ? $result : null, $rpslText);
            }
        } else {
            $errorMessages = is_array($result) ? (array) ($result['error_messages'] ?? []) : ['Objeto não retornado pela API do TC.'];
            $object->markFailed(implode('; ', $errorMessages) ?: 'Falha desconhecida ao publicar no TC.');
        }

        return $decoded;
    }

    /**
     * Espelha o objeto recém-publicado no catálogo manual (irr_objects),
     * usado hoje só como inventário de leitura — mantém os dois em sincronia
     * sem duplicar o cadastro que o operador faz aqui (irr_routes/irr_as_sets).
     *
     * @param array<string, mixed>|null $apiObjectResult
     */
    private function syncIrrObjectCatalog(
        IrrMaintainer $maintainer,
        IrrRoute|IrrAsSet $object,
        ?array $apiObjectResult,
        string $rpslText,
    ): void {
        [$type, $key] = $object instanceof IrrRoute
            ? [$object->attributeName(), $object->prefix]
            : ['as-set', $object->name];

        $acceptedText = $this->acceptedObjectText($apiObjectResult) ?? $rpslText;

        IrrObject::query()->updateOrCreate(
            ['object_type' => $type, 'object_key' => $key, 'source' => 'TC'],
            [
                'maintainer' => $maintainer->mntner,
                'status' => 'active',
                'raw_text' => $acceptedText,
                'last_synced_at' => now(),
                'active' => true,
            ]
        );
    }

    /**
     * @param array<string, mixed>|null $apiObjectResult
     */
    private function acceptedObjectText(?array $apiObjectResult): ?string
    {
        $text = $apiObjectResult['new_object_text'] ?? $apiObjectResult['submitted_object_text'] ?? null;

        return is_string($text) && trim($text) !== '' ? $text : null;
    }

    /**
     * @param array<string, mixed> $maskedRequest
     * @param array<string, mixed>|null $responsePayload
     */
    private function recordFailure(IrrRoute|IrrAsSet $object, string $operation, array $maskedRequest, ?array $responsePayload, string $error): array
    {
        IrrSubmission::query()->create([
            'submittable_type' => $object::class,
            'submittable_id' => $object->getKey(),
            'operation' => $operation,
            'request_payload' => $maskedRequest,
            'response_payload' => $responsePayload,
            'successful' => false,
        ]);

        $object->markFailed($error);

        return ['successful' => false, 'error' => $error];
    }
}
