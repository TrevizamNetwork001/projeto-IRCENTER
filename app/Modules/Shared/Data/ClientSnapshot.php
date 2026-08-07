<?php

namespace App\Modules\Shared\Data;

final readonly class ClientSnapshot
{
    public function __construct(
        public int $id,
        public ?string $clientCode,
        public string $legalName,
        public ?string $tradeName,
        public ?string $document,
        public ?string $email,
        public ?string $phone,
        public ?string $postalCode,
        public ?string $street,
        public ?string $addressNumber,
        public ?string $addressComplement,
        public ?string $district,
        public ?string $city,
        public ?string $state,
        public ?string $country,
        public bool $active,
    ) {
    }

    public function displayName(): string
    {
        return $this->tradeName ?: $this->legalName;
    }
}
