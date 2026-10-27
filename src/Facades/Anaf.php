<?php

namespace Gabrieldev\Anaf\Facades;

use Gabrieldev\Anaf\AnafClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Gabrieldev\Anaf\Data\Company|null company(string|int $cui)
 * @method static \Illuminate\Support\Collection<string, \Gabrieldev\Anaf\Data\Company> companies(iterable $cuis)
 * @method static void forget(string|int $cui)
 *
 * @see AnafClient
 */
class Anaf extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AnafClient::class;
    }
}
