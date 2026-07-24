<?php

namespace App\Http\Requests;

class StoreExternalIntegrationRequest
    extends ExternalIntegrationRequest
{
    public function rules(): array
    {
        return $this->integrationRules();
    }
}
