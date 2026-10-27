<?php

namespace Gabrieldev\Anaf\Rules;

use Closure;
use Gabrieldev\Anaf\AnafClient;
use Gabrieldev\Anaf\AnafException;
use Gabrieldev\Anaf\Cui;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Passes when the CUI is valid, exists at ANAF and the company is active (not inactive or deregistered).
 * If ANAF is down the rule passes by default, so a public outage never blocks your checkout;
 * use ActiveCompany::strict() to fail instead.
 */
class ActiveCompany implements ValidationRule
{
    public function __construct(private readonly bool $passWhenUnavailable = true) {}

    public static function strict(): self
    {
        return new self(false);
    }

    public static function check(mixed $value, bool $passWhenUnavailable = true): bool
    {
        if (! Cui::isValid($value)) {
            return false;
        }

        try {
            return (bool) app(AnafClient::class)->company((string) $value)?->isActive();
        } catch (AnafException) {
            return $passWhenUnavailable;
        }
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Cui::isValid($value)) {
            $fail('anaf::validation.cui')->translate();
        } elseif (! self::check($value, $this->passWhenUnavailable)) {
            $fail('anaf::validation.inactive')->translate();
        }
    }
}
