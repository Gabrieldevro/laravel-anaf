<?php

namespace Gabrieldev\Anaf\Rules;

use Closure;
use Gabrieldev\Anaf\Cui;
use Illuminate\Contracts\Validation\ValidationRule;

/** Offline check of the CUI/CIF control digit, with or without the "RO" prefix. */
class ValidCui implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Cui::isValid($value)) {
            $fail('anaf::validation.cui')->translate();
        }
    }
}
