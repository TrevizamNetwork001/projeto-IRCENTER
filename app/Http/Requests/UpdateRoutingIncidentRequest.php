<?php

namespace App\Http\Requests;

class UpdateRoutingIncidentRequest extends RoutingIncidentRequest
{
    public function rules(): array
    {
        return $this->incidentRules();
    }
}
