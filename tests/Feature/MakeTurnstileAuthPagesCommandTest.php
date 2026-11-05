<?php

namespace Eii\FilamentTurnstile\Tests\Feature;

use Eii\FilamentTurnstile\Tests\TestCase;
use Illuminate\Support\Facades\File;

class MakeTurnstileAuthPagesCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        File::delete(app_path('Filament/Pages/Auth/TrunstileLogin.php'));
        File::delete(app_path('Filament/Pages/Auth/TrunstileRegister.php'));
        File::delete(app_path('Filament/Pages/Auth/TrunstileRequestPasswordReset.php'));

        parent::tearDown();
    }

    public function test_login_command_creates_filament_5_schema_stub(): void
    {
        $this->artisan('make:filament-turnstile-login')->assertSuccessful();

        $path = app_path('Filament/Pages/Auth/TrunstileLogin.php');

        $this->assertTrue(File::exists($path));

        $contents = File::get($path);

        $this->assertStringContainsString('class TrunstileLogin extends Login', $contents);
        $this->assertStringContainsString('use Filament\\Schemas\\Schema;', $contents);
        $this->assertStringContainsString('public function form(Schema $schema): Schema', $contents);
        $this->assertStringContainsString("Turnstile::make('turnstile_token')", $contents);
    }

    public function test_register_command_creates_register_stub(): void
    {
        $this->artisan('make:filament-turnstile-register')->assertSuccessful();

        $path = app_path('Filament/Pages/Auth/TrunstileRegister.php');

        $this->assertTrue(File::exists($path));

        $contents = File::get($path);

        $this->assertStringContainsString('class TrunstileRegister extends Register', $contents);
        $this->assertStringContainsString('public function form(Schema $schema): Schema', $contents);
    }

    public function test_request_password_reset_command_creates_request_password_reset_stub(): void
    {
        $this->artisan('make:filament-turnstile-request-password-reset')->assertSuccessful();

        $path = app_path('Filament/Pages/Auth/TrunstileRequestPasswordReset.php');

        $this->assertTrue(File::exists($path));

        $contents = File::get($path);

        $this->assertStringContainsString('extends RequestPasswordReset', $contents);
        $this->assertStringContainsString('public function form(Schema $schema): Schema', $contents);
    }

    public function test_default_actions_are_configured(): void
    {
        $this->assertSame('admin-login', config('filament-turnstile.actions.login'));
        $this->assertSame('admin-register', config('filament-turnstile.actions.register'));
        $this->assertSame('admin-password-reset-request', config('filament-turnstile.actions.request_password_reset'));
    }
}
