<?php

namespace Eii\FilamentTurnstile\Forms\Components;

use Eii\FilamentTurnstile\Rules\ValidTurnstile;
use Filament\Forms\Components\Field;

class Turnstile extends Field
{
    protected string $view = 'filament-turnstile::forms.components.turnstile';

    protected ?string $theme = null;

    protected ?string $size = null;

    protected string|\Closure|null $turnstileAction = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->required();
        $this->label('');
        $this->rules([
            new ValidTurnstile(fn(): string => $this->getTurnstileAction()),
        ]);
    }

    public function getSiteKey(): ?string
    {
        $siteKey = config('filament-turnstile.site_key');

        if (! is_string($siteKey) || trim($siteKey) === '') {
            logger()->warning('Eii Turnstile: site key is not configured (TURNSTILE_SITE_KEY).');
            return null;
        }

        return $siteKey;
    }

    public function turnstileAction(string|\Closure|null $action): static
    {
        $this->turnstileAction = $action;

        return $this;
    }

    public function getTurnstileAction(): string
    {
        $action = $this->turnstileAction;

        if ($action instanceof \Closure) {
            $action = $action();
        }

        if (is_string($action) && trim($action) !== '') {
            return $action;
        }

        return (string) config('filament-turnstile.action', 'default');
    }

    public function theme(?string $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function size(?string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getTheme(): string
    {
        return $this->theme ?? (string) config('filament-turnstile.theme', 'auto');
    }

    public function getSize(): string
    {
        return $this->size ?? (string) config('filament-turnstile.size', 'normal');
    }
}
