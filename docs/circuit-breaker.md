# Circuit Breaker for Cloudflare Turnstile Verification

> **Status:** Planned — not yet implemented.
>
> This document captures the problem, design considerations, and a suggested
> implementation path for adding a circuit breaker to the Turnstile verification
> flow. Revisit when ready to begin work.

---

## The Problem

The current verification flow in `CloudflareTurnstileVerifier::verify()` makes a
synchronous HTTP call to Cloudflare's siteverify endpoint on every login,
registration, and password-reset attempt.

When Cloudflare's API is degraded or unreachable, the following happens:

1. **Every request blocks** for up to `connect_timeout` (3 s) + `timeout` (8 s)
   before failing.
2. **User experience degrades** — login forms feel broken; users retry,
   compounding load.
3. **Server resources are consumed** — PHP workers are tied up waiting for a
   remote service that will never respond in time.
4. **Cascading failure risk** — if the application has limited worker
   capacity (e.g. a small pool of PHP-FPM workers or a queue worker), a
   Cloudflare outage can exhaust the pool and take down unrelated
   functionality.

The `isTransient()` method on `TurnstileVerification` already identifies
failures that *could* be short-circuited (`connection_error`,
`siteverify_http_error`, `unexpected_error`), but every request still pays the
full timeout cost.

---

## What Is a Circuit Breaker?

A circuit breaker is a state machine with three states:

| State      | Behavior                                                        |
|------------|-----------------------------------------------------------------|
| **Closed** | Requests flow normally. Failures are counted.                   |
| **Open**   | Requests are rejected immediately without contacting Cloudflare.|
| **Half-Open** | A single probe request is allowed through to test recovery.   |

Transitions:

```
CLOSED ──(failure threshold reached)──▶ OPEN
  ▲                                      │
  │                                  (cooldown expires)
  │                                      ▼
  └──────(probe succeeds)────────── HALF-OPEN
         (probe fails) ──────────────▶ OPEN
```

---

## Design Considerations

### Fail Open vs. Fail Closed

This is the most important decision and has **security implications**.

| Strategy      | Behavior when circuit is open                          | Risk                                    |
|---------------|--------------------------------------------------------|-----------------------------------------|
| **Fail open** | Skip Turnstile; allow the request through.             | Attackers bypass CAPTCHA during outage. |
| **Fail closed** | Reject the request; user sees an error message.      | Legitimate users locked out during outage. |

**Recommendation:** Default to **fail closed**. The purpose of Turnstile is to
prevent abuse; silently disabling it during an outage defeats the purpose. The
`service_unavailable` translation key already provides a user-friendly message
for this case.

Consider exposing the strategy as a config option so operators can choose:

```php
// config/filament-turnstile.php
'circuit_breaker' => [
    'enabled' => env('TURNSTILE_CIRCUIT_BREAKER', true),
    'fail_open' => env('TURNSTILE_CIRCUIT_FAIL_OPEN', false),
    'failure_threshold' => 5,      // failures before opening
    'cooldown_seconds' => 120,     // how long to stay open
    'half_open_max_attempts' => 1, // probe requests before deciding
],
```

### Storage Backend

The circuit state must be shared across requests and workers.

| Option           | Pros                          | Cons                              |
|------------------|-------------------------------|-----------------------------------|
| **Cache (file)** | Zero config, works everywhere | Not shared across servers         |
| **Cache (Redis)**| Shared, fast, atomic          | Requires Redis dependency         |
| **Cache (DB)**   | Shared, no extra dependency   | Slower, adds DB load              |
| **In-memory**    | Fast                          | Per-process; useless with FPM/pool|

**Recommendation:** Use Laravel's `Cache` facade. It respects whatever driver
the application has configured. Document that Redis/Memcached is recommended
for multi-server deployments.

### Concurrency and Race Conditions

Multiple requests may fail simultaneously and all try to increment the failure
counter or open the circuit. Use atomic operations:

- `Cache::increment()` for failure counting (atomic in Redis/Memcached).
- `Cache::add()` for setting the open-circuit flag (only succeeds if key doesn't
  exist, preventing race conditions on cooldown expiry).

### Half-Open Probing

When the cooldown expires, the circuit enters half-open state. The first
request (or a small batch) is allowed through to Cloudflare:

- **If it succeeds** → close the circuit, reset failure count.
- **If it fails** → re-open the circuit, restart the cooldown.

Use `Cache::add()` with a short TTL to allow exactly N probe requests, then
decide based on their outcomes.

---

## Suggested Implementation

### 1. Add config keys

```php
// config/filament-turnstile.php

'circuit_breaker' => [
    'enabled' => (bool) env('TURNSTILE_CIRCUIT_BREAKER', true),
    'fail_open' => (bool) env('TURNSTILE_CIRCUIT_FAIL_OPEN', false),
    'failure_threshold' => (int) env('TURNSTILE_CIRCUIT_FAILURE_THRESHOLD', 5),
    'cooldown_seconds' => (int) env('TURNSTILE_CIRCUIT_COOLDOWN', 120),
],
```

### 2. Create a dedicated class

Keep the circuit logic out of the verifier. A single-responsibility class is
easier to test and swap.

```
src/Services/CircuitBreaker.php
```

Suggested interface:

```php
<?php

namespace Eii\FilamentTurnstile\Services;

final class CircuitBreaker
{
    public function isOpen(): bool;

    public function recordSuccess(): void;

    public function recordFailure(): void;

    public function allowRequest(): bool;
}
```

### 3. Integrate into the verifier

```php
// Inside CloudflareTurnstileVerifier::verify(), before the HTTP call:

$circuit = app(CircuitBreaker::class);

if (! $circuit->allowRequest()) {
    $failOpen = (bool) config('filament-turnstile.circuit_breaker.fail_open', false);

    if ($failOpen) {
        Log::warning('Eii Turnstile: circuit breaker open — failing open (skipping verification).');
        return new TurnstileVerification(success: true, reason: 'circuit_breaker_fail_open');
    }

    Log::error('Eii Turnstile: circuit breaker open — rejecting request.');
    return $this->reject('circuit_breaker_open');
}

// ... existing HTTP call ...

// On success:
$circuit->recordSuccess();

// In the catch blocks (ConnectionException, Throwable):
$circuit->recordFailure();
```

### 4. Update the DTO

Add `circuit_breaker_open` and `circuit_breaker_fail_open` to the transient
reasons list in `TurnstileVerification::isTransient()`.

### 5. Update the validation rule

The `match` expression in `ValidTurnstile` should handle the new reasons:

```php
$messageKey = match ($result->reason) {
    'connection_error',
    'siteverify_http_error',
    'circuit_breaker_open' => 'filament-turnstile::messages.service_unavailable',

    'circuit_breaker_fail_open' => null, // no failure — request proceeds

    default => 'filament-turnstile::messages.invalid',
};

if ($messageKey !== null) {
    $fail(__($messageKey));
}
```

### 6. Add tests

- Circuit opens after N consecutive failures.
- Circuit rejects requests while open.
- Circuit allows a probe request after cooldown expires.
- Successful probe closes the circuit.
- Failed probe re-opens the circuit.
- `fail_open` returns a successful verification.
- `fail_closed` returns a rejection.
- `recordSuccess()` resets the failure counter.

---

## Open Questions

- [ ] Should the failure counter be scoped per-action (login vs. register) or
      global across all verification attempts?
- [ ] Should there be a metric/event emitted when the circuit opens/closes,
      so operators can hook it into monitoring (e.g. Sentry, Datadog)?
- [ ] Should the circuit breaker respect the `allow_missing_action` config in
      any way, or should it be completely independent?
- [ ] Is there a need for a manual reset command (e.g. `php artisan
      turnstile:reset-circuit`) for ops teams during incidents?

---

## References

- [Martin Fowler — Circuit Breaker](https://martinfowler.com/bliki/CircuitBreaker.html)
- [Laravel Cache — Atomic locks & increments](https://laravel.com/docs/cache#atomic-locks)
- Current transient failure reasons: `connection_error`, `siteverify_http_error`,
  `unexpected_error` (see `TurnstileVerification::isTransient()`).