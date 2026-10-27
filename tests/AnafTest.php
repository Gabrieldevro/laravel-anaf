<?php

namespace Gabrieldev\Anaf\Tests;

use Gabrieldev\Anaf\AnafClient;
use Gabrieldev\Anaf\AnafException;
use Gabrieldev\Anaf\AnafServiceProvider;
use Gabrieldev\Anaf\Cui;
use Gabrieldev\Anaf\Facades\Anaf;
use Gabrieldev\Anaf\Rules\ActiveCompany;
use Gabrieldev\Anaf\Rules\ValidCui;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class AnafTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AnafServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('anaf.route.prefix', 'api/anaf');
        $app['config']->set('anaf.route.middleware', []);
    }

    private function fakeAnaf(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/fixtures/anaf-response.json'), true);

        Http::fake(['webservicesp.anaf.ro/*' => function (Request $request) use ($fixture) {
            $asked = array_column($request->data(), 'cui');

            return Http::response([
                'found' => array_values(array_filter($fixture['found'], fn ($f) => in_array($f['date_generale']['cui'], $asked, true))),
                'notFound' => array_values(array_diff($asked, array_map(fn ($f) => $f['date_generale']['cui'], $fixture['found']))),
            ]);
        }]);
    }

    public static function cuiProvider(): array
    {
        return [
            'plain' => ['45845765', true],
            'with RO prefix' => ['RO45845765', true],
            'lowercase with spaces' => [' ro 14399840 ', true],
            'integer' => [14399840, true],
            'wrong control digit' => ['45845766', false],
            'letters' => ['RO12AB', false],
            'too long' => ['12345678901', false],
            'leading zero' => ['0123', false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('cuiProvider')]
    public function test_cui_control_digit(mixed $cui, bool $valid): void
    {
        $this->assertSame($valid, Cui::isValid($cui));
    }

    public function test_it_maps_a_company(): void
    {
        $this->fakeAnaf();

        $company = Anaf::company('RO45845765');

        $this->assertSame('45845765', $company->cui);
        $this->assertSame('BLOOMERI BRAND S.R.L.', $company->name);
        $this->assertSame('J2022005504408', $company->registrationNumber);
        $this->assertFalse($company->vatPayer);
        $this->assertSame('45845765', $company->fiscalCode());
        $this->assertTrue($company->isActive());
        $this->assertSame('Mun. Iaşi', $company->headquarters->locality);
        $this->assertSame('IS', $company->headquarters->countyCode);
        $this->assertSame('Bld. Nicolae Iorga 7B, cam. 3', $company->headquarters->line());
        $this->assertSame('2022-03-23', $company->registeredAt->toDateString());
    }

    public function test_vat_payers_get_the_ro_prefix_on_invoices(): void
    {
        $this->fakeAnaf();

        $billing = Anaf::company('14399840')->toBilling();

        $this->assertSame('RO14399840', $billing['fiscal_code']);
        $this->assertSame('DANTE INTERNATIONAL SA', $billing['company']);
        $this->assertTrue($billing['vat_payer']);
        $this->assertSame('RO', $billing['country']);
        $this->assertSame('060787', $billing['postal_code']);
    }

    public function test_batch_lookup_keeps_order_and_skips_unknown_and_invalid(): void
    {
        $this->fakeAnaf();

        $companies = Anaf::companies(['14399840', 'RO45845765', '45845766', '18']);

        $this->assertEquals(['14399840', '45845765'], $companies->keys()->all());
        $this->assertSame('DANTE INTERNATIONAL SA', $companies['14399840']->name);
        Http::assertSentCount(1);
    }

    public function test_results_are_cached(): void
    {
        $this->fakeAnaf();

        Anaf::company('45845765');
        Anaf::company('RO45845765');

        Http::assertSentCount(1);
    }

    public function test_invalid_cui_never_calls_anaf(): void
    {
        Http::fake();

        $this->assertNull(Anaf::company('123'));
        Http::assertNothingSent();
    }

    public function test_errors_throw_an_anaf_exception(): void
    {
        Http::fake(['*' => Http::response('<html>Request Rejected</html>', 403)]);

        $this->expectException(AnafException::class);
        app(AnafClient::class)->company('45845765');
    }

    public function test_validation_rules(): void
    {
        $this->fakeAnaf();

        $this->assertTrue(Validator::make(['cui' => 'RO45845765'], ['cui' => [new ValidCui]])->passes());
        $this->assertTrue(Validator::make(['cui' => '45845766'], ['cui' => 'anaf_cui'])->fails());
        $this->assertTrue(Validator::make(['cui' => '45845765'], ['cui' => [new ActiveCompany]])->passes());
        $this->assertTrue(Validator::make(['cui' => '45845765'], ['cui' => 'anaf_active'])->passes());
    }

    public function test_active_company_rule_passes_when_anaf_is_down_unless_strict(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->assertTrue(Validator::make(['cui' => '45845765'], ['cui' => [new ActiveCompany]])->passes());
        $this->assertTrue(Validator::make(['cui' => '45845765'], ['cui' => [ActiveCompany::strict()]])->fails());
    }

    public function test_autocomplete_endpoint(): void
    {
        $this->fakeAnaf();

        $this->getJson('/api/anaf/RO14399840')
            ->assertOk()
            ->assertJsonPath('data.company', 'DANTE INTERNATIONAL SA')
            ->assertJsonPath('data.fiscal_code', 'RO14399840')
            ->assertJsonPath('data.active', true);

        $this->getJson('/api/anaf/123')->assertStatus(422);
    }
}
