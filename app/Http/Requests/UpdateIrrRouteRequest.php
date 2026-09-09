<?php

namespace App\Http\Requests;

class UpdateIrrRouteRequest extends IrrRouteRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->routeRules($this->route('irr_route')?->id);
    }
}
