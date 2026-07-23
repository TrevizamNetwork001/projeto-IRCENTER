<?php

namespace App\Http\Requests;

use App\Models\IrrObject;

class UpdateIrrObjectRequest extends IrrObjectRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $irrObject = $this->route('irr_object');

        return $this->irrRules(
            $irrObject instanceof IrrObject ? $irrObject : null
        );
    }
}
