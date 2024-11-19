<?php

namespace VisualBuilder\ExportScheduler;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;

class ExportSchedulerPlugin implements Plugin
{
    use EvaluatesClosures;

    protected bool | Closure $navigation = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-export-scheduler';
    }

    public function register(Panel $panel): void
    {
        $panel->resources(config('export-scheduler.resources'));
    }

    public function boot(Panel $panel): void {}

    public function enableNavigation(bool | Closure $callback = true): static
    {
        $this->navigation = $callback;

        return $this;
    }

    public function shouldRegisterNavigation(): bool
    {
        return $this->evaluate($this->navigation) === true ?? config('export-scheduler.navigation.enabled', true);
    }
}
