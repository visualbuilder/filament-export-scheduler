<?php

namespace Visualbuilder\ExportScheduler;

use Filament\Actions\Exports\Models\Export;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Visualbuilder\ExportScheduler\Commands\ExportSchedulerCommand;
use Visualbuilder\ExportScheduler\Contracts\ResolvesReportUsers;
use Visualbuilder\ExportScheduler\Support\ReportUserResolver;

class ExportSchedulerServiceProvider extends PackageServiceProvider
{
    public static string $name = 'export-scheduler';

    public static string $viewNamespace = 'export-scheduler';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasViews('export-scheduler')
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishAssets()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->copyAndRegisterServiceProviderInApp()
                    ->askToStarRepoOnGitHub('visualbuilder/filament-export-scheduler');
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }

        //Add Polymorphic relationship to Export
        Export::polymorphicUserRelationship();
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            ExportSchedulerCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            '2026_08_10_000001_create_custom_reports_table',
            '2026_08_10_000002_create_scheduled_reports_table',
            '2026_08_10_000003_migrate_export_schedules_to_custom_reports',
        ];
    }

    public function packageRegistered(): void
    {
        parent::packageRegistered();

        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang/');

        // Bind the ExportScheduler class to the container
        $this->app->singleton(ExportScheduler::class, function () {
            return new ExportScheduler;
        });

        // Every user id, label and email address the package reads goes through
        // this. Rebind it in your own provider to change how users are named or
        // addressed application-wide.
        $this->app->singleton(ResolvesReportUsers::class, function () {
            return app(config('export-scheduler.user_resolver', ReportUserResolver::class));
        });
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        // publish seeders
        if (app()->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../database/seeders/CustomReportSeeder.php' => database_path('seeders/CustomReportSeeder.php'),
            ], 'export-scheduler-seeders');
        }

        if(app()->environment('testing')) {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        }

    }

    protected function getAssetPackageName(): ?string
    {
        return 'visualbuilder/filament-export-scheduler';
    }


    /**
     * @return array<string>
     */
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }
}
