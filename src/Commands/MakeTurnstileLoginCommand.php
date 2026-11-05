<?php

namespace Eii\FilamentTurnstile\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeTurnstileLoginCommand extends Command
{
    protected $signature = 'make:filament-turnstile-login {--force : Overwrite the file if it already exists}';

    protected $description = 'Create a Turnstile-protected Filament login page class';

    public function handle(): int
    {
        $targetPath = app_path('Filament/Pages/Auth/TurnstileLogin.php');

        if (File::exists($targetPath) && ! $this->option('force')) {
            $this->error('File already exists: ' . $targetPath);
            $this->line('Use --force to overwrite it.');

            return self::FAILURE;
        }

        $stubPath = __DIR__ . '/../../resources/stubs/login.stub';

        if (! File::exists($stubPath)) {
            $this->error('Stub file not found: ' . $stubPath);

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($targetPath));

        File::put($targetPath, File::get($stubPath));

        $this->info('Created: ' . $targetPath);
        $this->newLine();
        $this->line('Next step: register it in your Filament panel provider:');
        $this->line("->login(\\App\\Filament\\Pages\\Auth\\TurnstileLogin::class)");

        return self::SUCCESS;
    }
}
