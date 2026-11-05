<?php

namespace Eii\FilamentTurnstile\Contracts;

use Eii\FilamentTurnstile\DTO\TurnstileVerification;

interface TurnstileVerifier
{
    public function verify(mixed $token, ?string $remoteIp = null, ?string $expectedAction = null): TurnstileVerification;
}
