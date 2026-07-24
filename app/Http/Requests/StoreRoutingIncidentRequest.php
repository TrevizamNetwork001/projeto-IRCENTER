<?php

namespace App\Http\Requests;

class StoreRoutingIncidentRequest extends RoutingIncidentRequest
{
    public function rules(): array
    {
        return $this->incidentRules();
    }
}
