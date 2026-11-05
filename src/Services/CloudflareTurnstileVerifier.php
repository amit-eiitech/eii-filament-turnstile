<?php

namespace Eii\FilamentTurnstile\Services;

use Eii\FilamentTurnstile\Contracts\TurnstileVerifier as TurnstileVerifierContract;
use Eii\FilamentTurnstile\DTO\TurnstileVerification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class CloudflareTurnstileVerifier implements TurnstileVerifierContract
{
    private const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(mixed $token, ?string $remoteIp = null, ?string $expectedAction = null): TurnstileVerification
    {
        if (! is_string($token) || trim($token) === '' || strlen($token) > 2048) {
            return $this->reject('invalid_token');
        }

        $secret = config('filament-turnstile.secret_key');

        if (! is_string($secret) || trim($secret) === '') {
            Log::error('Eii Turnstile: secret key is not configured.');

            return $this->reject('missing_secret');
        }

        $allowedHostnames = array_values(array_filter(array_map(
            static fn($hostname): string => strtolower(trim((string) $hostname)),
            config('filament-turnstile.allowed_hostnames', []),
        )));

        // Fail closed: an empty allowlist must never mean "allow every hostname".
        if ($allowedHostnames === []) {
            Log::error('Eii Turnstile: hostname allowlist is empty.');

            return $this->reject('empty_hostname_allowlist');
        }

        try {
            $response = Http::asForm()
                ->connectTimeout((int) config('filament-turnstile.connect_timeout', 3))
                ->timeout((int) config('filament-turnstile.timeout', 8))
                ->post(self::SITEVERIFY_URL, array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                    // Reuse this idempotency key if you later add safe HTTP retries.
                    'idempotency_key' => (string) Str::uuid(),
                ], static fn($value): bool => $value !== null && $value !== ''));

            if (! $response->successful()) {
                Log::warning('Eii Turnstile: Siteverify returned an HTTP error.', [
                    'http_status' => $response->status(),
                ]);

                return $this->reject('siteverify_http_error');
            }

            $data = $response->json();

            if (! is_array($data)) {
                return $this->reject('invalid_siteverify_response');
            }

            $hostname = is_string($data['hostname'] ?? null)
                ? strtolower($data['hostname'])
                : null;

            $action = is_string($data['action'] ?? null)
                ? $data['action']
                : null;

            $errorCodes = is_array($data['error-codes'] ?? null)
                ? $data['error-codes']
                : [];

            if (($data['success'] ?? false) !== true) {
                Log::notice('Eii Turnstile: Cloudflare rejected the token.', [
                    'error_codes' => $errorCodes,
                ]);

                return TurnstileVerification::failed(
                    reason: 'cloudflare_rejected_token',
                    errorCodes: $errorCodes,
                    hostname: $hostname,
                    action: $action,
                );
            }

            if ($hostname === null || ! in_array($hostname, $allowedHostnames, true)) {
                Log::warning('Eii Turnstile: hostname validation failed.', [
                    'received_hostname' => $hostname,
                    'allowed_hostnames' => $allowedHostnames,
                ]);

                return TurnstileVerification::failed(
                    reason: 'hostname_mismatch',
                    hostname: $hostname,
                    action: $action,
                );
            }

            $expectedAction = $expectedAction !== null && trim($expectedAction) !== ''
                ? $expectedAction
                : (string) config('filament-turnstile.action', 'default');

            $allowMissingAction = (bool) config('filament-turnstile.allow_missing_action', false);

            if ($action === null && ! $allowMissingAction) {
                return TurnstileVerification::failed(
                    reason: 'missing_action',
                    hostname: $hostname,
                );
            }

            if ($action !== null && ! hash_equals($expectedAction, $action)) {
                Log::warning('Eii Turnstile: action validation failed.', [
                    'expected_action' => $expectedAction,
                    'received_action' => $action,
                ]);

                return TurnstileVerification::failed(
                    reason: 'action_mismatch',
                    hostname: $hostname,
                    action: $action,
                );
            }

            return new TurnstileVerification(
                success: true,
                hostname: $hostname,
                action: $action,
                errorCodes: $errorCodes,
            );
        } catch (ConnectionException $exception) {
            report($exception->setMessage(
                "Turnstile: Cloudflare connection failed (timeout/connect issue)"
            ));

            return $this->reject('connection_error');
        } catch (Throwable $exception) {
            report(new \RuntimeException(
                "Turnstile: unexpected error during verification",
                previous: $exception,
            ));

            return $this->reject('unexpected_error');
        }
    }

    private function reject(string $reason): TurnstileVerification
    {
        return TurnstileVerification::failed($reason);
    }
}
