<?php

namespace Gabrieldev\Anaf\Data;

use JsonSerializable;

final class Address implements JsonSerializable
{
    public function __construct(
        public readonly ?string $street,
        public readonly ?string $number,
        public readonly ?string $locality,
        public readonly ?string $county,
        public readonly ?string $countyCode,
        public readonly ?string $postalCode,
        public readonly ?string $details,
        public readonly ?string $country,
    ) {}

    /** @param array<string, mixed> $raw ANAF block, $p = key prefix ("s" for headquarters, "d" for fiscal domicile) */
    public static function fromAnaf(array $raw, string $p): self
    {
        $get = fn (string $key) => filled($raw[$p.$key] ?? null) ? trim((string) $raw[$p.$key]) : null;

        return new self(
            street: $get('denumire_Strada'),
            number: $get('numar_Strada'),
            locality: $get('denumire_Localitate'),
            county: $get('denumire_Judet'),
            countyCode: $get('cod_JudetAuto'),
            postalCode: self::postalCode($get('cod_Postal')),
            details: $get('detalii_Adresa'),
            country: $get('tara') ?? 'România',
        );
    }

    /** ANAF stores postal codes as numbers, so Bucharest's "060787" comes back as "60787". */
    public static function postalCode(?string $code): ?string
    {
        return $code !== null && ctype_digit($code) && strlen($code) < 6 ? str_pad($code, 6, '0', STR_PAD_LEFT) : $code;
    }

    /** "Bld. Nicolae Iorga 7B, cam. 3" */
    public function line(): string
    {
        return collect([trim(($this->street ?? '').' '.($this->number ?? '')), $this->details])->filter()->implode(', ');
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this) + ['line' => $this->line()];
    }
}
