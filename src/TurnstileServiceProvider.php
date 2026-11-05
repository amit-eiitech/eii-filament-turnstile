<?php

namespace Eii\FilamentTurnstile;

use Eii\FilamentTurnstile\Commands\MakeTurnstileLoginCommand;
use Eii\FilamentTurnstile\Commands\MakeTurnstileRegisterCommand;
use Eii\FilamentTurnstile\Commands\MakeTurnstileRequestPasswordResetCommand;
use Eii\FilamentTurnstile\Contracts\TurnstileVerifier as TurnstileVerifierContract;
use Eii\FilamentTurnstile\Services\CloudflareTurnstileVerifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class TurnstileServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/filament-turnstile.php',
            'filament-turnstile',
        );

        $this->app->singleton(
            TurnstileVerifierContract::class,
            CloudflareTurnstileVerifier::class,
        );
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-turnstile');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'filament-turnstile');

        $this->publishes([
            __DIR__ . '/../config/filament-turnstile.php' => config_path('filament-turnstile.php'),
        ], 'filament-turnstile-config');

        $this->app->booted(fn() => $this->validateConfig());

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeTurnstileLoginCommand::class,
                MakeTurnstileRegisterCommand::class,
                MakeTurnstileRequestPasswordResetCommand::class,
            ]);
        }
    }

    private function validateConfig(): void
    {
        $secret = config('filament-turnstile.secret_key');

        if (! is_string($secret) || trim($secret) === '') {
            Log::error('Eii Turnstile: TURNSTILE_SECRET_KEY is not configured. Turnstile verification will fail.');
        }

        $siteKey = config('filament-turnstile.site_key');

        if (! is_string($siteKey) || trim($siteKey) === '') {
            Log::error('Eii Turnstile: TURNSTILE_SITE_KEY is not configured. The widget will not render.');
        }

        $allowedHostnames = config('filament-turnstile.allowed_hostnames', []);

        if (empty($allowedHostnames)) {
            Log::error('Eii Turnstile: TURNSTILE_ALLOWED_HOSTNAMES is empty. All verifications will be rejected.');
        }
    }
}
