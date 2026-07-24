<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CpfCnpj implements ValidationRule
{
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        $document = preg_replace('/\D/', '', (string) $value);

        $valid = match (strlen($document)) {
            11 => $this->validCpf($document),
            14 => $this->validCnpj($document),
            default => false,
        };

        if (! $valid) {
            $fail('Informe um CPF ou CNPJ válido.');
        }
    }

    private function validCpf(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;

            for ($index = 0; $index < $position; $index++) {
                $sum += (int) $cpf[$index] * ($position + 1 - $index);
            }

            $digit = (10 * $sum) % 11;
            $digit = $digit === 10 ? 0 : $digit;

            if ($digit !== (int) $cpf[$position]) {
                return false;
            }
        }

        return true;
    }

    private function validCnpj(string $cnpj): bool
    {
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $weights = [
            [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
            [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        ];

        foreach ($weights as $digitIndex => $weightSet) {
            $sum = 0;

            foreach ($weightSet as $index => $weight) {
                $sum += (int) $cnpj[$index] * $weight;
            }

            $remainder = $sum % 11;
            $digit = $remainder < 2 ? 0 : 11 - $remainder;

            if ($digit !== (int) $cnpj[12 + $digitIndex]) {
                return false;
            }
        }

        return true;
    }
}
