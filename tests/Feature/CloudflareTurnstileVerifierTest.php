<?php

namespace Eii\FilamentTurnstile\Tests\Feature;

use Eii\FilamentTurnstile\Contracts\TurnstileVerifier;
use Eii\FilamentTurnstile\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class CloudflareTurnstileVerifierTest extends TestCase
{
    public function test_it_accepts_token_when_expected_action_matches(): void
    {
        config()->set('filament-turnstile.secret_key', 'secret');
        config()->set('filament-turnstile.allowed_hostnames', ['localhost']);
        config()->set('filament-turnstile.allow_missing_action', false);

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
                'action' => 'admin-login',
                'error-codes' => [],
            ], 200),
        ]);

        $result = app(TurnstileVerifier::class)->verify('token', '127.0.0.1', 'admin-login');

        $this->assertTrue($result->success);
    }

    public function test_it_rejects_token_when_expected_action_does_not_match(): void
    {
        config()->set('filament-turnstile.secret_key', 'secret');
        config()->set('filament-turnstile.allowed_hostnames', ['localhost']);
        config()->set('filament-turnstile.allow_missing_action', false);

        Http::fake([
            '*' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
                'action' => 'admin-register',
                'error-codes' => [],
            ], 200),
        ]);

        $result = app(TurnstileVerifier::class)->verify('token', '127.0.0.1', 'admin-login');

        $this->assertFalse($result->success);
        $this->assertSame('action_mismatch', $result->reason);
    }
}
