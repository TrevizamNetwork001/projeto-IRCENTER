<?php

namespace App\Modules\Shared\Infrastructure;

use App\Models\Client;
use App\Modules\Shared\Contracts\ClientDirectory;
use App\Modules\Shared\Data\ClientSnapshot;

final class CoreClientDirectory implements ClientDirectory
{
    public function find(int $clientId): ?ClientSnapshot
    {
        $client = Client::query()->find($clientId);

        if (! $client) {
            return null;
        }

        return new ClientSnapshot(
            id: (int) $client->id,
            clientCode: $client->client_code,
            legalName: $client->legal_name,
            tradeName: $client->trade_name,
            document: $client->document,
            email: $client->email,
            phone: $client->phone,
            postalCode: $client->postal_code,
            street: $client->street,
            addressNumber: $client->address_number,
            addressComplement: $client->address_complement,
            district: $client->district,
            city: $client->city,
            state: $client->state,
            country: $client->country,
            active: (bool) $client->active,
        );
    }
}
