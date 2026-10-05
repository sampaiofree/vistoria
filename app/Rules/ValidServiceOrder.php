<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidServiceOrder implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/\A[0-9]{10}\z/', $value) !== 1) {
            $fail('A OS deve conter exatamente 10 dígitos numéricos.');
        }
    }
}
