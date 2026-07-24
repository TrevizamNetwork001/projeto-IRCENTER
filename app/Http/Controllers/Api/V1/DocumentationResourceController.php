<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AutonomousSystem;
use App\Models\Client;
use App\Models\Prefix;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentationResourceController extends Controller
{
    public function clients(Request $request): JsonResponse
    {
        $clients = Client::query()
            ->select([
                'id',
                'client_code',
                'contract_number',
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

        return response()->json($clients);
    }

    public function client(Client $client): JsonResponse
    {
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
            'data' => $client->only([
                'id',
                'client_code',
                'contract_number',
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
            ]) + [
                'autonomous_systems' =>
                    $client->autonomousSystems,
                'prefixes' => $client->prefixes,
            ],
        ]);
    }

    public function users(Request $request): JsonResponse
    {
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

        return response()->json($users);
    }

    public function autonomousSystems(
        Request $request
    ): JsonResponse {
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

        return response()->json($systems);
    }

    public function prefixes(Request $request): JsonResponse
    {
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

        return response()->json($prefixes);
    }

    private function perPage(Request $request): int
    {
        return min(
            max($request->integer('per_page', 25), 1),
            100
        );
    }
}
