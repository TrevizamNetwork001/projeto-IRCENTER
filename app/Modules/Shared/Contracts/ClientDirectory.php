<?php

namespace App\Modules\Shared\Contracts;

use App\Modules\Shared\Data\ClientSnapshot;

interface ClientDirectory
{
    public function find(int $clientId): ?ClientSnapshot;
}
