<?php

namespace App\Http\Requests;

class StoreIrrAsSetRequest extends IrrAsSetRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->asSetRules();
    }
}
