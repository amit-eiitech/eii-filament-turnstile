# Eii Filament Turnstile

Cloudflare Turnstile integration for Filament 5 forms and auth pages. It provides a reusable Filament field, mandatory server-side Siteverify validation, and scaffold commands for Login, Register, and Request Password Reset pages.

## Requirements

- PHP 8.2+
- Laravel 11.28+
- Filament Forms 5.x

## Install from a local path (development)

In the Laravel application's `composer.json`, add a path repository:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../eii-filament-turnstile",
      "options": { "symlink": true }
    }
  ]
}
```

Adjust the path to the package directory, then run:

```bash
composer require eii/filament-turnstile:@dev
```

When published to Packagist, the normal install command will be:

```bash
composer require eii/filament-turnstile
```

Laravel auto-discovers the service provider.

## Configure

Publish the config:

```bash
php artisan vendor:publish --tag=filament-turnstile-config
```

Add to `.env`:

```env
TURNSTILE_SITE_KEY=your-site-key
TURNSTILE_SECRET_KEY=your-secret-key
TURNSTILE_ALLOWED_HOSTNAMES=yourdomain.com
TURNSTILE_ALLOW_MISSING_ACTION=false
```

The hostname allowlist is mandatory. An empty list fails closed. Use comma-separated hostnames without scheme, path, or port.

`TURNSTILE_ALLOW_MISSING_ACTION` should remain `false` in production. It exists only for local test-key responses that omit the action field.

Action names, widget theme/size, and HTTP timeouts are now controlled in `config/filament-turnstile.php` (not in `.env`).

Example `config/filament-turnstile.php` actions section:

```php
'actions' => [
    'login' => 'admin-login',
    'register' => 'admin-register',
    'request_password_reset' => 'admin-password-reset-request',
],
```

## Local testing

Cloudflare provides [dummy site keys and secret keys](https://developers.cloudflare.com/turnstile/troubleshooting/testing/) that work on any domain including `localhost`. Use these during development and in automated tests — no real Cloudflare account required.

### Test keys

| Site key | Secret key | Behavior |
|----------|-----------|----------|
| `1x00000000000000000000AA` | `1x0000000000000000000000000000000AA` | Always passes validation |
| `2x00000000000000000000AB` | `2x0000000000000000000000000000000AA` | Always fails validation |
| `1x00000000000000000000AA` | `3x0000000000000000000000000000000AA` | Always fails — "token already spent" |
| `3x00000000000000000000FF` | `1x0000000000000000000000000000000AA` | Forces interactive challenge, then passes |

### Example `.env` for local development

```env
TURNSTILE_SITE_KEY=1x00000000000000000000AA
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA
TURNSTILE_ALLOWED_HOSTNAMES=example.com
TURNSTILE_ALLOW_MISSING_ACTION=true
```

> **Note:** Test keys return `hostname: "example.com"` and `action: "test"` in the siteverify response. Set `TURNSTILE_ALLOWED_HOSTNAMES=example.com` and `TURNSTILE_ALLOW_MISSING_ACTION=true` when using test keys.

### Example `.env` for testing rejection flow

```env
TURNSTILE_SITE_KEY=2x00000000000000000000AB
TURNSTILE_SECRET_KEY=2x0000000000000000000000000000000AA
TURNSTILE_ALLOWED_HOSTNAMES=example.com
TURNSTILE_ALLOW_MISSING_ACTION=true
```

Use this to verify that your forms correctly reject failed validations and display error messages.

### Important

- Test keys return `hostname: "example.com"` in the siteverify response. You must include `example.com` in `TURNSTILE_ALLOWED_HOSTNAMES` when testing.
- Test keys return `action: "test"` in the siteverify response. Set `TURNSTILE_ALLOW_MISSING_ACTION=true` to skip action validation, or set `'action' => 'test'` in your config.
- Test keys generate dummy tokens (`XXXX.DUMMY.TOKEN.XXXX`). Test secret keys only accept dummy tokens; production secret keys only accept real tokens.
- Never commit test keys to production `.env` files.
- Production site keys should **not** include `localhost` or `127.0.0.1` in their allowed domains.

## Use in a Filament form

```php
use Eii\FilamentTurnstile\Forms\Components\Turnstile;
use Filament\Schemas\Schema;

public function form(Schema $schema): Schema
{
    return $schema->components([
        // Your other fields...
        Turnstile::make('turnstile_token')
            ->label('Security Verification')
            ->turnstileAction('admin-users-create')
            ->theme('auto')
            ->size('normal')
            ->appearance('interaction-only'),
    ]);
}
```

Use different actions per page/form so Cloudflare analytics can distinguish traffic:

- `admin-login`
- `admin-register`
- `admin-password-reset-request`
- `admin-users-create`
- `admin-orders-update`

The field is required and automatically applies server-side Siteverify validation. The field's state contains the token. Do not trust the browser callback alone.

After a failed submission, you can dispatch a reset event from the Livewire component:

```php
$this->dispatch('eii-turnstile-reset');
```

## Direct verifier usage

```php
use Eii\FilamentTurnstile\Contracts\TurnstileVerifier;

$result = app(TurnstileVerifier::class)->verify(
    token: $token,
    remoteIp: request()->ip(),
    expectedAction: 'admin-login',
);

if (! $result->success) {
    // Reject the request.
}
```

## Security notes

- Siteverify is always called server-side during field validation.
- Tokens are limited to 2,048 characters and Cloudflare enforces token expiry and single-use semantics.
- Hostname validation is mandatory and exact.
- Action validation is mandatory unless `TURNSTILE_ALLOW_MISSING_ACTION=true` is explicitly configured.
- Secret keys and tokens are never written to package logs.
- API failures fail closed.

## Auth page scaffolding (Filament 5)

Generate Turnstile-enabled auth pages:

```bash
php artisan make:filament-turnstile-login
php artisan make:filament-turnstile-register
php artisan make:filament-turnstile-request-password-reset
```

Generated classes:

- `App\\Filament\\Pages\\Auth\\TurnstileLogin`
- `App\\Filament\\Pages\\Auth\\TurnstileRegister`
- `App\\Filament\\Pages\\Auth\\TurnstileRequestPasswordReset`

Then wire them into your panel provider (example):

```php
->login(\App\Filament\Pages\Auth\TurnstileLogin::class)
```

Registration and password reset wiring methods can differ slightly between Filament versions and panel setup styles, so register the generated classes in your panel's auth-page configuration accordingly.

## Current scope

Version 1.0.0 includes reusable field + verification engine + auth page scaffolding commands for Login, Register, and Request Password Reset.
