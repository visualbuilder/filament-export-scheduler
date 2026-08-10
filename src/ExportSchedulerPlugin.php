<?php

namespace Visualbuilder\ExportScheduler;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;

class ExportSchedulerPlugin implements Plugin
{
    use EvaluatesClosures;

    /**
     * Set by enableNavigation(), which now governs both resources at once so
     * existing calls keep working after the split.
     */
    protected bool|Closure|null $navigation = null;

    protected bool|Closure|null $reportNavigation = null;

    protected bool|Closure|null $scheduleNavigation = null;

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

    public function boot(Panel $panel): void
    {
    }

    /**
     * Gate both nav items at once. Kept so existing callers such as
     * `->enableNavigation(fn () => $user->can('...'))` need no change.
     */
    public function enableNavigation(bool|Closure $callback = true): static
    {
        $this->navigation = $callback;

        return $this;
    }

    public function enableReportNavigation(bool|Closure $callback = true): static
    {
        $this->reportNavigation = $callback;

        return $this;
    }

    public function enableScheduleNavigation(bool|Closure $callback = true): static
    {
        $this->scheduleNavigation = $callback;

        return $this;
    }

    /**
     * Resolution order, most specific first: the per-resource setter, then the
     * shared setter, then config.
     */
    public function shouldRegisterReportNavigation(): bool
    {
        return $this->evaluate($this->reportNavigation)
            ?? $this->evaluate($this->navigation)
            ?? config('export-scheduler.navigation.reports.enabled', true);
    }

    public function shouldRegisterScheduleNavigation(): bool
    {
        return $this->evaluate($this->scheduleNavigation)
            ?? $this->evaluate($this->navigation)
            ?? config('export-scheduler.navigation.schedules.enabled', true);
    }

    /**
     * @deprecated 6.0.0 The single resource became two. Use
     *             shouldRegisterReportNavigation() or shouldRegisterScheduleNavigation().
     */
    public function shouldRegisterNavigation(): bool
    {
        return $this->evaluate($this->navigation)
            ?? config('export-scheduler.navigation.reports.enabled', true);
    }
}
