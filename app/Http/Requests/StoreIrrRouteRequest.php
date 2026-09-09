<?php

namespace App\Http\Requests;

class StoreIrrRouteRequest extends IrrRouteRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->routeRules();
    }
}
