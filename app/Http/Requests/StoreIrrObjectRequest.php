<?php

namespace App\Http\Requests;

class StoreIrrObjectRequest extends IrrObjectRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->irrRules();
    }
}
