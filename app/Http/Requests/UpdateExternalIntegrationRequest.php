<?php

namespace App\Http\Requests;

class UpdateExternalIntegrationRequest
    extends ExternalIntegrationRequest
{
    public function rules(): array
    {
        return $this->integrationRules();
    }
}
