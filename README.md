<h1 align="center">🇷🇴 laravel-anaf</h1>

<p align="center"><b>Look up any Romanian company by CUI/CIF from the official ANAF API</b> — name, address, VAT status, e-Factura, inactive status — and autofill your billing forms in one line.<br>
<i>Caută orice firmă din România după CUI direct la ANAF și completează automat datele de facturare.</i></p>

<p align="center">
<img alt="tests" src="https://github.com/Gabrieldevro/laravel-anaf/actions/workflows/tests.yml/badge.svg">
<img alt="Laravel" src="https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20?style=flat-square&logo=laravel&logoColor=white">
<img alt="PHP" src="https://img.shields.io/badge/PHP-%E2%89%A58.2-777BB4?style=flat-square&logo=php&logoColor=white">
<img alt="License" src="https://img.shields.io/badge/license-MIT-blue?style=flat-square">
</p>

---

```php
$company = Anaf::company('RO14399840');

$company->name;            // "DANTE INTERNATIONAL SA"
$company->vatPayer;        // true
$company->fiscalCode();    // "RO14399840"  (RO prefix only for VAT payers)
$company->isActive();      // true
$company->toBilling();     // ready for your checkout form ↓
```

```php
[
    'company' => 'DANTE INTERNATIONAL SA',
    'fiscal_code' => 'RO14399840',
    'registration_number' => 'J2002000372404',
    'address' => 'Şos. Virtuţii 148, spatiul E47',
    'city' => 'Sector 6 Mun. Bucureşti',
    'county' => 'MUNICIPIUL BUCUREŞTI',
    'postal_code' => '060787',
    'country' => 'RO',
    'vat_payer' => true,
]
```

## Why

Every Romanian shop, SaaS or invoicing app asks B2B customers for their company details. Customers mistype them, and invoices with a wrong CUI or a missing `RO` prefix get rejected in e-Factura. ANAF has a **free public API** with the official data, but it is easy to get wrong:

- it rejects malformed CUIs at the firewall with an HTML page instead of JSON;
- it allows about **1 request per second** and at most 100 CUIs per request;
- the response is deeply nested Romanian keys (`date_generale.denumire`, `inregistrare_scop_Tva.scpTVA`…);
- postal codes come back as numbers (`060787` → `60787`).

This package handles all of that.

## Features

- ✅ **Offline CUI check** with the official control digit (key `753217532`), with or without `RO`. Invalid CUIs never hit the API.
- 🏢 **Typed `Company` object**: name, address, Trade Register number, CAEN, legal form, registration date, IBAN, fiscal authority.
- 🧾 **Tax status**: VAT payer, VAT on collection (TVA la încasare), split VAT, **e-Factura registry**, inactive / deregistered.
- 📍 **Structured addresses** for the registered office and fiscal domicile (street, number, locality, county, county code, postal code).
- ⚡ **Cached** (24h by default) and **batched** (100 CUIs per request) to stay far under ANAF's rate limit.
- 🛡️ **Validation rules**: `anaf_cui` (offline) and `anaf_active` (company exists and is active), with RO/EN messages.
- 🔌 **Optional JSON endpoint** for billing-form autocomplete.
- 🧪 Fully tested with `Http::fake()`; no API key, no account.

## Installation

```bash
composer require gabrieldevro/laravel-anaf
```

The service provider and the `Anaf` facade are registered automatically. Publishing the config is optional:

```bash
php artisan vendor:publish --tag=anaf-config
```

## Usage

### One company

```php
use Gabrieldev\Anaf\Facades\Anaf;

$company = Anaf::company('RO45845765'); // null if the CUI is invalid or unknown

if ($company && ! $company->isActive()) {
    // inactive since $company->inactiveSince, or deregistered on $company->deregisteredAt
}

$company->eInvoice;               // registered in the RO e-Factura registry
$company->headquarters->county;   // "IAŞI"
$company->headquarters->countyCode; // "IS"
$company->caen;                   // "4712"
$company->raw;                    // the untouched ANAF payload
```

### Many companies at once

```php
$companies = Anaf::companies(['14399840', 'RO45845765', 'not-a-cui']);
// Collection keyed by CUI, in your order. Invalid / unknown CUIs are skipped.
```

### Validation

```php
use Gabrieldev\Anaf\Rules\{ValidCui, ActiveCompany};

$request->validate([
    'cui' => ['required', new ValidCui],          // control digit only, no network
    'cui' => ['required', new ActiveCompany],     // exists at ANAF and is active
    'cui' => ['required', ActiveCompany::strict()], // also fail when ANAF is down
]);

// or as strings
$request->validate(['cui' => 'required|anaf_cui|anaf_active']);
```

`ActiveCompany` **passes when ANAF is unreachable** so a public outage never blocks your checkout. Use `strict()` if you'd rather fail.

### Billing autocomplete endpoint

Set a prefix and you get `GET /api/anaf/{cui}`:

```env
ANAF_ROUTE_PREFIX=api/anaf
```

```js
const res = await fetch(`/api/anaf/${cui}`);
if (res.ok) {
  const { data } = await res.json();
  form.company.value = data.company;
  form.fiscal_code.value = data.fiscal_code;
  form.address.value = data.address;
  form.city.value = data.city;
  form.county.value = data.county;
}
// 404 = not found, 422 = invalid CUI, 503 = ANAF is down
```

It is throttled (`throttle:20,1`) by default; change `anaf.route.middleware` to fit your app.

### Errors

Unknown CUIs return `null`. Network problems and ANAF errors throw `Gabrieldev\Anaf\AnafException`:

```php
try {
    $company = Anaf::company($cui);
} catch (AnafException $e) {
    // ask the customer to type the details manually
}
```

## Configuration

| Key | Env | Default |
|---|---|---|
| `endpoint` | `ANAF_ENDPOINT` | `https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva` |
| `timeout` | `ANAF_TIMEOUT` | `10` seconds |
| `cache_ttl` | `ANAF_CACHE_TTL` | `86400` (24h, `0` disables) |
| `cache_store` | `ANAF_CACHE_STORE` | default store |
| `route.prefix` | `ANAF_ROUTE_PREFIX` | `null` (endpoint off) |

Clear one company from the cache with `Anaf::forget($cui)`.

## Testing

```bash
composer test
```

---

## 🇷🇴 Pe scurt, în română

Pachet Laravel care caută firme după **CUI/CIF** direct în API-ul public **ANAF** (fără cont, fără cheie): denumire, adresă, nr. Registrul Comerțului, CAEN, **plătitor de TVA**, TVA la încasare, split TVA, **e-Factura**, **inactiv/radiat**.

- `Anaf::company('RO123…')->toBilling()` îți dă direct câmpurile pentru formularul de facturare pe firmă.
- Reguli de validare `anaf_cui` (cifra de control, fără internet) și `anaf_active` (firma există și e activă).
- Rezultatele se țin în cache 24h și cererile se grupează câte 100, ca să nu depășești limita ANAF de ~1 cerere/secundă.
- Endpoint opțional `GET /api/anaf/{cui}` pentru completarea automată în checkout.

Mesajele de validare sunt în română și engleză.

## License

MIT © [Gabriel Dev](https://gabrieldev.eu) — web development, Laravel & e-commerce in Romania and Norway.

If this saved you time, a ⭐ helps other developers find it.
