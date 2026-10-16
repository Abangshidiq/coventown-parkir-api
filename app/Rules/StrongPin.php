<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPin implements ValidationRule
{
    /**
     * Tolak PIN yang terlalu mudah ditebak.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pin = (string) $value;

        $sama     = preg_match('/^(\d)\1{5}$/', $pin) === 1;                 // 111111, 000000
        $urut     = str_contains('01234567890', $pin)                        // 123456, 234567
                    || str_contains('09876543210', $pin);                    // 654321
        $berulang = preg_match('/^(\d{2})\1\1$/', $pin) === 1                // 121212
                    || preg_match('/^(\d{3})\1$/', $pin) === 1;              // 123123

        if ($sama || $urut || $berulang) {
            $fail('PIN terlalu mudah ditebak. Gunakan kombinasi angka yang lebih acak.');
        }
    }
}