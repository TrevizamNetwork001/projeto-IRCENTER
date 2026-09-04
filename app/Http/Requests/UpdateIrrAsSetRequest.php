<?php

namespace App\Http\Requests;

class UpdateIrrAsSetRequest extends IrrAsSetRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->asSetRules($this->route('irr_as_set')?->id);
    }
}
