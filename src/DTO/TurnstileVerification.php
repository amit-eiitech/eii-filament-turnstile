<?php

namespace Eii\FilamentTurnstile\DTO;

final readonly class TurnstileVerification
{
    public function __construct(
        public bool $success,
        public ?string $hostname = null,
        public ?string $action = null,
        public array $errorCodes = [],
        public ?string $reason = null,
    ) {}

    public function isTransient(): bool
    {
        return in_array($this->reason, [
            'connection_error',
            'siteverify_http_error',
            'unexpected_error',
        ], true);
    }

    public static function failed(string $reason, array $errorCodes = [], ?string $hostname = null, ?string $action = null): self
    {
        return new self(
            success: false,
            hostname: $hostname,
            action: $action,
            errorCodes: $errorCodes,
            reason: $reason,
        );
    }
}
