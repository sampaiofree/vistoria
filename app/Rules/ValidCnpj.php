<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidCnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits)) {
            $fail('Informe um CNPJ válido.');

            return;
        }

        $first = $this->digit(substr($digits, 0, 12), [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        $second = $this->digit(substr($digits, 0, 12).$first, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

        if ($digits !== substr($digits, 0, 12).$first.$second) {
            $fail('Informe um CNPJ válido.');
        }
    }

    /** @param array<int, int> $weights */
    private function digit(string $value, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += (int) $value[$index] * $weight;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }
}
