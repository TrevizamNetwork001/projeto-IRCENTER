<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BrazilianPhone implements ValidationRule
{
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        $digits = preg_replace('/\D/', '', (string) $value);

        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }

        if (! in_array(strlen($digits), [10, 11], true)) {
            $fail('Informe um telefone brasileiro válido com DDD.');
            return;
        }

        if (preg_match('/^(\d)\1+$/', $digits)) {
            $fail('Informe um telefone brasileiro válido com DDD.');
            return;
        }

        $areaCode = substr($digits, 0, 2);

        if (
            $areaCode[0] === '0'
            || $areaCode[1] === '0'
        ) {
            $fail('Informe um DDD válido.');
            return;
        }

        if (strlen($digits) === 11 && $digits[2] !== '9') {
            $fail('O celular deve possuir o dígito 9 após o DDD.');
        }
    }
}
