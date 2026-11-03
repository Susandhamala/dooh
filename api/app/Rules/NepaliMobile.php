<?php

namespace App\Rules;

use App\Support\NepaliPhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NepaliMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || NepaliPhone::normalise($value) === null) {
            $fail('Enter a valid Nepali mobile number, like 98XXXXXXXX.');
        }
    }
}
