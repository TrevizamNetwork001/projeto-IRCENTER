<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;
use App\Models\User;
use App\Support\DocumentationApiScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentationResourceController extends Controller
{
    public function clients(Request $request): JsonResponse
    {
        $this->validateListRequest($request, ['active_only']);

        $clients = Client::query()
            ->select([
                'id',
                'client_code',
                'legal_name',
                'trade_name',
                'document',
                'email',
                'phone',
                'website',
                'postal_code',
                'street',
                'address_number',
                'address_complement',
                'district',
                'city',
                'state',
                'country',
                'active',
                'updated_at',
            ])
            ->when(
                $request->boolean('active_only'),
                fn ($query) => $query->where('active', true)
            )
            ->orderBy('legal_name')
            ->paginate($this->perPage($request));

        $clients->through(
            fn (Client $client): array => $this->clientData(
                $client,
                $this->canReadPii($request)
            )
        );

        return response()->json($clients);
    }

    public function client(
        Request $request,
        Client $client
    ): JsonResponse {
        $client->load([
            'autonomousSystems' => fn ($query) => $query
                ->select([
                    'id',
                    'client_id',
                    'asn',
                    'name',
                    'description',
                    'rir',
                    'country',
                    'noc_contact',
                    'noc_email',
                    'noc_phone',
                    'active',
                    'updated_at',
                ])
                ->orderBy('asn'),
            'prefixes' => fn ($query) => $query
                ->select([
                    'id',
                    'client_id',
                    'autonomous_system_id',
                    'prefix',
                    'ip_version',
                    'description',
                    'rir',
                    'country',
                    'allocation_status',
                    'purpose',
                    'active',
                    'updated_at',
                ])
                ->orderBy('ip_version')
                ->orderBy('prefix'),
        ]);

        return response()->json([
            'data' => $this->clientData(
                $client,
                $this->canReadPii($request)
            ) + [
                'autonomous_systems' => $client->autonomousSystems->map(
                    fn (AutonomousSystem $system): array => $this->autonomousSystemData(
                        $system,
                        $this->canReadPii($request)
                    )
                ),
                'prefixes' => $client->prefixes->map(
                    fn (Prefix $prefix): array => $this->prefixData($prefix)
                ),
            ],
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $this->validateListRequest($request, ['active_only']);

        $users = User::query()
            ->select([
                'id',
                'name',
                'email',
                'role',
                'active',
                'updated_at',
            ])
            ->when(
                $request->boolean('active_only'),
                fn ($query) => $query->where('active', true)
            )
            ->orderBy('name')
            ->paginate($this->perPage($request));

        $users->through(
            fn (User $user): array => $this->userData(
                $user,
                $this->canReadPii($request)
            )
        );

        return response()->json($users);
    }

    public function autonomousSystems(
        Request $request
    ): JsonResponse {
        $this->validateListRequest($request, [
            'active_only',
            'client_id',
        ]);

        $systems = AutonomousSystem::query()
            ->with([
                'client:id,client_code,legal_name,trade_name',
            ])
            ->select([
                'id',
                'client_id',
                'asn',
                'name',
                'description',
                'rir',
                'country',
                'website',
                'noc_contact',
                'noc_email',
                'noc_phone',
                'active',
                'updated_at',
            ])
            ->when(
                $request->filled('client_id'),
                fn ($query) => $query->where(
                    'client_id',
                    $request->integer('client_id')
                )
            )
            ->when(
                $request->boolean('active_only'),
                fn ($query) => $query->where('active', true)
            )
            ->orderBy('asn')
            ->paginate($this->perPage($request));

        $systems->through(
            fn (AutonomousSystem $system): array => $this->autonomousSystemData(
                $system,
                $this->canReadPii($request)
            )
        );

        return response()->json($systems);
    }

    public function prefixes(Request $request): JsonResponse
    {
        $this->validateListRequest($request, [
            'active_only',
            'client_id',
            'ip_version',
        ]);

        $prefixes = Prefix::query()
            ->with([
                'client:id,client_code,legal_name,trade_name',
                'autonomousSystem:id,asn,name',
            ])
            ->select([
                'id',
                'client_id',
                'autonomous_system_id',
                'prefix',
                'ip_version',
                'description',
                'rir',
                'country',
                'allocation_status',
                'purpose',
                'active',
                'updated_at',
            ])
            ->when(
                $request->filled('client_id'),
                fn ($query) => $query->where(
                    'client_id',
                    $request->integer('client_id')
                )
            )
            ->when(
                in_array(
                    $request->integer('ip_version'),
                    [4, 6],
                    true
                ),
                fn ($query) => $query->where(
                    'ip_version',
                    $request->integer('ip_version')
                )
            )
            ->when(
                $request->boolean('active_only'),
                fn ($query) => $query->where('active', true)
            )
            ->orderBy('ip_version')
            ->orderBy('prefix')
            ->paginate($this->perPage($request));

        $prefixes->through(
            fn (Prefix $prefix): array => $this->prefixData($prefix)
        );

        return response()->json($prefixes);
    }

    private function perPage(Request $request): int
    {
        return min(
            max($request->integer('per_page', 25), 1),
            100
        );
    }

    /**
     * @param  list<string>  $filters
     */
    private function validateListRequest(
        Request $request,
        array $filters
    ): void {
        $rules = [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
        ];

        if (in_array('active_only', $filters, true)) {
            $rules['active_only'] = [
                'sometimes',
                'in:true,false,1,0',
            ];
        }

        if (in_array('client_id', $filters, true)) {
            $rules['client_id'] = ['sometimes', 'integer', 'min:1'];
        }

        if (in_array('ip_version', $filters, true)) {
            $rules['ip_version'] = ['sometimes', 'integer', 'in:4,6'];
        }

        $request->validate($rules);
    }

    /**
     * @return array<string, mixed>
     */
    private function clientData(Client $client, bool $includePii): array
    {
        $data = $client->only([
            'id',
            'client_code',
            'legal_name',
            'trade_name',
            'website',
            'active',
            'updated_at',
        ]);

        if ($includePii) {
            $data += $client->only([
                'document',
                'email',
                'phone',
                'postal_code',
                'street',
                'address_number',
                'address_complement',
                'district',
                'city',
                'state',
                'country',
            ]);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function userData(User $user, bool $includePii): array
    {
        $data = $user->only([
            'id',
            'name',
            'role',
            'active',
            'updated_at',
        ]);

        if ($includePii) {
            $data += $user->only(['email']);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function autonomousSystemData(
        AutonomousSystem $system,
        bool $includePii
    ): array {
        $data = $system->only([
            'id',
            'client_id',
            'asn',
            'name',
            'description',
            'rir',
            'country',
            'website',
            'active',
            'updated_at',
        ]);

        if ($includePii) {
            $data += $system->only([
                'noc_contact',
                'noc_email',
                'noc_phone',
            ]);
        }

        if ($system->relationLoaded('client') && $system->client !== null) {
            $data['client'] = $system->client->only([
                'id',
                'client_code',
                'legal_name',
                'trade_name',
            ]);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function prefixData(Prefix $prefix): array
    {
        $data = $prefix->only([
            'id',
            'client_id',
            'autonomous_system_id',
            'prefix',
            'ip_version',
            'description',
            'rir',
            'country',
            'allocation_status',
            'purpose',
            'active',
            'updated_at',
        ]);

        if ($prefix->relationLoaded('client') && $prefix->client !== null) {
            $data['client'] = $prefix->client->only([
                'id',
                'client_code',
                'legal_name',
                'trade_name',
            ]);
        }

        if (
            $prefix->relationLoaded('autonomousSystem')
            && $prefix->autonomousSystem !== null
        ) {
            $data['autonomous_system'] =
                $prefix->autonomousSystem->only(['id', 'asn', 'name']);
        }

        return $data;
    }

    private function canReadPii(Request $request): bool
    {
        $apiClient = $request->attributes->get('api_client');

        // LEGACY COMPATIBILITY: legacy token retains the prior payload.
        return $apiClient === 'legacy'
            || (
                $apiClient instanceof ApiClient
                && in_array(
                    DocumentationApiScope::PII_READ,
                    $apiClient->scopes ?? [],
                    true
                )
            );
    }
}
