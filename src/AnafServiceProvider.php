<?php

namespace Gabrieldev\Anaf;

use Gabrieldev\Anaf\Http\CompanyController;
use Gabrieldev\Anaf\Rules\ActiveCompany;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AnafServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/anaf.php', 'anaf');

        $this->app->singleton(AnafClient::class, fn ($app) => new AnafClient(
            $app->make(Http::class),
            $app['cache']->store($app['config']->get('anaf.cache_store')),
            $app['config']->get('anaf'),
        ));
        $this->app->alias(AnafClient::class, 'anaf');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'anaf');

        $this->publishes([__DIR__.'/../config/anaf.php' => config_path('anaf.php')], 'anaf-config');
        $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/anaf')], 'anaf-lang');

        // 'cui' => 'required|anaf_cui'  (valid control digit; no API call)
        // 'cui' => 'required|anaf_active'  (exists at ANAF and is active)
        Validator::extend('anaf_cui', fn ($attribute, $value) => Cui::isValid($value), trans('anaf::validation.cui'));
        Validator::extend('anaf_active', fn ($attribute, $value) => ActiveCompany::check($value), trans('anaf::validation.inactive'));

        if ($prefix = config('anaf.route.prefix')) {
            Route::middleware(config('anaf.route.middleware', ['api']))
                ->get(trim($prefix, '/').'/{cui}', CompanyController::class)
                ->name('anaf.company');
        }
    }
}
