# Eii Filament Turnstile

Cloudflare Turnstile integration for Filament 5 forms and auth scaffolding, with action-aware validation and analytics-friendly tracking.

This package gives you:

- a reusable `Turnstile` Filament form component,
- strict server-side Cloudflare Siteverify validation,
- scaffolding commands for custom Filament auth pages (Login, Register, Request Password Reset),
- per-form/per-page action support so you can reuse the same site/secret keys while still tracking each flow separately.

## Requirements

- PHP 8.2+
- Laravel 11.28+ (also supports 12 and 13 via Illuminate constraints)
- Filament Forms 5.x

## Installation

### Local path install (development)

In your Laravel app `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../eii-filament-turnstile",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

Then require it:

```bash
composer require eii/filament-turnstile:@dev
```

### Packagist install

```bash
composer require eii/filament-turnstile
```

Laravel will auto-discover the service provider.

## Configuration

Publish config:

```bash
php artisan vendor:publish --tag=filament-turnstile-config
```

Add required env values:

```env
TURNSTILE_SITE_KEY=your-site-key
TURNSTILE_SECRET_KEY=your-secret-key
TURNSTILE_ALLOWED_HOSTNAMES=yourdomain.com
TURNSTILE_ALLOW_MISSING_ACTION=false
```

### Important behavior

- `TURNSTILE_ALLOWED_HOSTNAMES` is mandatory and fail-closed (empty list rejects all).
- Hostnames must be comma-separated plain hosts (no scheme/path/port).
- Keep `TURNSTILE_ALLOW_MISSING_ACTION=false` in production.
- Action names, widget options, and HTTP timeouts are configured in `config/filament-turnstile.php`.

Example actions block:

```php
'actions' => [
    'login' => 'admin-login',
    'register' => 'admin-register',
    'request_password_reset' => 'admin-password-reset-request',
],
```

## Why actions matter (tracking + security)

You can use the same Turnstile site key and secret key across multiple forms/pages, while assigning a unique action per flow.

Example actions:

- `admin-login`
- `admin-register`
- `admin-password-reset-request`
- `admin-users-create`
- `admin-orders-update`

Benefits:

- Cloudflare analytics can distinguish traffic by flow.
- Server-side validation checks the expected action to prevent token reuse across unrelated forms.

## Use in a Filament form

```php
use Eii\FilamentTurnstile\Forms\Components\Turnstile;
use Filament\Schemas\Schema;

public function form(Schema $schema): Schema
{
    return $schema->components([
        // ... your fields
        Turnstile::make('turnstile_token')
            ->label('Security Verification')
            ->turnstileAction('admin-users-create')
            ->theme('auto')
            ->size('normal')
            ->appearance('interaction-only'),
    ]);
}
```

Notes:

- Field is required.
- Token is verified server-side via Cloudflare Siteverify.
- Don’t trust frontend callback alone.

After a failed submit, you can reset the widget from Livewire:

```php
$this->dispatch('eii-turnstile-reset');
```

## Auth page scaffolding commands (Filament 5)

Generate custom Turnstile-enabled auth pages:

```bash
php artisan make:filament-turnstile-login
php artisan make:filament-turnstile-register
php artisan make:filament-turnstile-request-password-reset
```

Each command supports `--force` to overwrite existing files.

Generated classes:

- `App\Filament\Pages\Auth\TurnstileLogin`
- `App\Filament\Pages\Auth\TurnstileRegister`
- `App\Filament\Pages\Auth\TurnstileRequestPasswordReset`

Register them in your Panel provider auth setup. Example:

```php
->login(\App\Filament\Pages\Auth\TurnstileLogin::class)
```

(Registration/password-reset wiring may differ depending on your panel config style.)

## Direct verifier usage

```php
use Eii\FilamentTurnstile\Contracts\TurnstileVerifier;

$result = app(TurnstileVerifier::class)->verify(
    token: $token,
    remoteIp: request()->ip(),
    expectedAction: 'admin-login',
);

if (! $result->success) {
    // reject request
}
```

## Local testing with Cloudflare test keys

Cloudflare provides official test keys that work on localhost and CI.
Reference: <https://developers.cloudflare.com/turnstile/troubleshooting/testing/>

| Site key | Secret key | Behavior |
|---|---|---|
| `1x00000000000000000000AA` | `1x0000000000000000000000000000000AA` | Always passes |
| `2x00000000000000000000AB` | `2x0000000000000000000000000000000AA` | Always fails |
| `1x00000000000000000000AA` | `3x0000000000000000000000000000000AA` | Fails (token already spent) |
| `3x00000000000000000000FF` | `1x0000000000000000000000000000000AA` | Interactive challenge then pass |

Example local env:

```env
TURNSTILE_SITE_KEY=1x00000000000000000000AA
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA
TURNSTILE_ALLOWED_HOSTNAMES=example.com
TURNSTILE_ALLOW_MISSING_ACTION=true
```

With test keys, Cloudflare commonly returns `hostname=example.com` and `action=test`, so configure accordingly for local tests.

## Security model

- Always validates with Cloudflare server-side.
- Rejects invalid/missing/oversized tokens.
- Enforces hostname allowlist.
- Enforces action match (unless missing action is explicitly allowed).
- Fails closed on API/network/config problems.
- Does not log secrets or tokens.

---

Current scope includes reusable field + verification service + auth-page scaffolding for Login, Register, and Request Password Reset.
