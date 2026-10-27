<?php

namespace Gabrieldev\Anaf\Data;

use Carbon\CarbonImmutable;
use JsonSerializable;

final class Company implements JsonSerializable
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public readonly string $cui,
        public readonly string $name,
        public readonly string $address,
        public readonly ?string $registrationNumber,
        public readonly ?string $phone,
        public readonly ?string $postalCode,
        public readonly ?string $caen,
        public readonly ?string $iban,
        public readonly ?string $legalForm,
        public readonly ?string $fiscalAuthority,
        public readonly ?string $registrationStatus,
        public readonly ?CarbonImmutable $registeredAt,
        public readonly bool $vatPayer,
        public readonly bool $vatOnCollection,
        public readonly bool $splitVat,
        public readonly bool $eInvoice,
        public readonly bool $inactive,
        public readonly ?CarbonImmutable $inactiveSince,
        public readonly ?CarbonImmutable $deregisteredAt,
        public readonly Address $headquarters,
        public readonly Address $fiscalAddress,
        public readonly array $raw,
    ) {}

    /** @param array<string, mixed> $raw one item of ANAF's "found" array */
    public static function fromAnaf(array $raw): self
    {
        $general = $raw['date_generale'] ?? [];
        $inactive = $raw['stare_inactiv'] ?? [];
        $text = fn (array $a, string $k) => filled($a[$k] ?? null) ? trim((string) $a[$k]) : null;
        $date = fn (mixed $v) => filled($v) ? CarbonImmutable::parse((string) $v) : null;

        return new self(
            cui: (string) ($general['cui'] ?? ''),
            name: trim((string) ($general['denumire'] ?? '')),
            address: trim((string) ($general['adresa'] ?? '')),
            registrationNumber: $text($general, 'nrRegCom'),
            phone: $text($general, 'telefon'),
            postalCode: Address::postalCode($text($general, 'codPostal')),
            caen: $text($general, 'cod_CAEN'),
            iban: $text($general, 'iban'),
            legalForm: $text($general, 'forma_juridica'),
            fiscalAuthority: $text($general, 'organFiscalCompetent'),
            registrationStatus: $text($general, 'stare_inregistrare'),
            registeredAt: $date($general['data_inregistrare'] ?? null),
            vatPayer: (bool) ($raw['inregistrare_scop_Tva']['scpTVA'] ?? false),
            vatOnCollection: (bool) ($raw['inregistrare_RTVAI']['statusTvaIncasare'] ?? false),
            splitVat: (bool) ($raw['inregistrare_SplitTVA']['statusSplitTVA'] ?? false),
            eInvoice: (bool) ($general['statusRO_e_Factura'] ?? false),
            inactive: (bool) ($inactive['statusInactivi'] ?? false),
            inactiveSince: ($inactive['statusInactivi'] ?? false) ? $date($inactive['dataInactivare'] ?? null) : null,
            deregisteredAt: $date($inactive['dataRadiere'] ?? null),
            headquarters: Address::fromAnaf($raw['adresa_sediu_social'] ?? [], 's'),
            fiscalAddress: Address::fromAnaf($raw['adresa_domiciliu_fiscal'] ?? [], 'd'),
            raw: $raw,
        );
    }

    /** The fiscal code as printed on invoices: "RO45845765" for VAT payers, "45845765" otherwise. */
    public function fiscalCode(): string
    {
        return ($this->vatPayer ? 'RO' : '').$this->cui;
    }

    /** Active = not flagged inactive and not deregistered (radiată). */
    public function isActive(): bool
    {
        return ! $this->inactive && $this->deregisteredAt === null;
    }

    /**
     * Ready-made billing fields for checkout and invoice forms.
     *
     * @return array<string, mixed>
     */
    public function toBilling(): array
    {
        return [
            'company' => $this->name,
            'fiscal_code' => $this->fiscalCode(),
            'registration_number' => $this->registrationNumber,
            'address' => $this->headquarters->line() ?: $this->address,
            'city' => $this->headquarters->locality,
            'county' => $this->headquarters->county,
            'postal_code' => $this->headquarters->postalCode ?? $this->postalCode,
            'country' => 'RO',
            'vat_payer' => $this->vatPayer,
        ];
    }

    public function jsonSerialize(): array
    {
        $data = get_object_vars($this);
        unset($data['raw']);

        return $data + ['fiscal_code' => $this->fiscalCode(), 'active' => $this->isActive()];
    }
}
