<?php

namespace Eii\FilamentTurnstile\Rules;

use Closure;
use Eii\FilamentTurnstile\Contracts\TurnstileVerifier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;

final class ValidTurnstile implements ValidationRule
{
    public function __construct(
        private readonly string|Closure|null $expectedAction = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $expectedAction = $this->expectedAction;

        if ($expectedAction instanceof Closure) {
            $expectedAction = $expectedAction();
        }

        $result = app(TurnstileVerifier::class)->verify(
            token: $value,
            remoteIp: request()->ip(),
            expectedAction: is_string($expectedAction) ? $expectedAction : null,
        );

        if (! $result->success) {
            if ($result->isTransient()) {
                Log::error('Eii Turnstile: transient verification failure — possible Cloudflare outage.', [
                    'reason' => $result->reason,
                ]);
            } else {
                Log::warning('Eii Turnstile: validation failed.', [
                    'reason' => $result->reason,
                    'error_codes' => $result->errorCodes,
                ]);
            }

            $messageKey = match ($result->reason) {
                'connection_error', 'siteverify_http_error' => 'filament-turnstile::messages.service_unavailable',
                default => 'filament-turnstile::messages.invalid',
            };

            $fail(__($messageKey));
        }
    }
}
