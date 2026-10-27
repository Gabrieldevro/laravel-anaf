<?php

namespace Gabrieldev\Anaf;

use Gabrieldev\Anaf\Data\Company;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Collection;
use Throwable;

class AnafClient
{
    /** ANAF accepts at most 100 CUIs per request. */
    public const BATCH_SIZE = 100;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
        private readonly array $config,
    ) {}

    /**
     * Looks up one company. Returns null when the CUI is invalid or ANAF does not know it.
     *
     * @throws AnafException when ANAF is unreachable or answers with an error
     */
    public function company(string|int $cui): ?Company
    {
        $cui = Cui::normalize($cui);

        return Cui::isValid($cui) ? $this->companies([$cui])->get($cui) : null;
    }

    /**
     * Looks up many companies at once (batched by 100), keyed by the normalized CUI.
     * Invalid or unknown CUIs are simply missing from the result.
     *
     * @param  iterable<string|int>  $cuis
     * @return Collection<string, Company>
     *
     * @throws AnafException
     */
    public function companies(iterable $cuis): Collection
    {
        $wanted = collect($cuis)->map(fn ($c) => Cui::normalize($c))->filter(fn ($c) => Cui::isValid($c))->unique()->values();

        $found = collect();
        $missing = [];
        foreach ($wanted as $cui) {
            $cached = $this->ttl() > 0 ? $this->cache->get($this->cacheKey($cui)) : null;
            is_array($cached) ? $found[$cui] = Company::fromAnaf($cached) : $missing[] = $cui;
        }

        foreach (array_chunk($missing, self::BATCH_SIZE) as $chunk) {
            foreach ($this->request($chunk) as $raw) {
                $company = Company::fromAnaf($raw);
                $found[$company->cui] = $company;
                if ($this->ttl() > 0) {
                    $this->cache->put($this->cacheKey($company->cui), $raw, $this->ttl());
                }
            }
        }

        // Keep the caller's order.
        return $wanted->filter(fn ($cui) => $found->has($cui))->mapWithKeys(fn ($cui) => [$cui => $found[$cui]]);
    }

    public function forget(string|int $cui): void
    {
        $this->cache->forget($this->cacheKey(Cui::normalize($cui)));
    }

    /**
     * @param  list<string>  $cuis
     * @return list<array<string, mixed>>
     */
    private function request(array $cuis): array
    {
        $date = now('Europe/Bucharest')->toDateString();

        try {
            $response = $this->http
                ->timeout((int) ($this->config['timeout'] ?? 10))
                ->retry(2, 1000, throw: false)
                ->withHeaders(['User-Agent' => 'gabrieldevro/laravel-anaf'])
                ->acceptJson()
                ->post($this->config['endpoint'], array_map(fn ($cui) => ['cui' => (int) $cui, 'data' => $date], $cuis));
        } catch (Throwable $e) {
            throw new AnafException('ANAF is unreachable: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful() || ! is_array($response->json('found'))) {
            throw new AnafException("ANAF answered with HTTP {$response->status()}: ".mb_substr(strip_tags($response->body()), 0, 200));
        }

        return $response->json('found');
    }

    private function cacheKey(string $cui): string
    {
        return 'anaf:company:'.$cui;
    }

    private function ttl(): int
    {
        return (int) ($this->config['cache_ttl'] ?? 0);
    }
}
