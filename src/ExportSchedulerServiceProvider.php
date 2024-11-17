<?php

namespace VisualBuilder\ExportScheduler;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Filesystem\Filesystem;
use Livewire\Features\SupportTesting\Testable;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use VisualBuilder\ExportScheduler\Commands\ExportSchedulerCommand;
use VisualBuilder\ExportScheduler\Testing\TestsExportScheduler;

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
            ->hasInstallCommand(function(InstallCommand $command) {
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
    }

    public function packageRegistered(): void {

        parent::packageRegistered();

        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang/');

        // Bind the ExportScheduler class to the container
        $this->app->singleton(ExportScheduler::class, function () {
            return new ExportScheduler();
        });
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

//        // Asset Registration
//        FilamentAsset::register(
//            $this->getAssets(),
//            $this->getAssetPackageName()
//        );

//        FilamentAsset::registerScriptData(
//            $this->getScriptData(),
//            $this->getAssetPackageName()
//        );

        // Icon Registration
//        FilamentIcon::register($this->getIcons());

        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/filament-export-scheduler/{$file->getFilename()}"),
                ], 'filament-export-scheduler-stubs');
            }
            $this->publishes([
                __DIR__.'/../database/seeders/ExportScheduleSeeder.php' => database_path('seeders/ExportScheduleSeeder.php'),

            ], 'filament-export-scheduler-seeds');
        }

    }

    protected function getAssetPackageName(): ?string
    {
        return 'visualbuilder/filament-export-scheduler';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            // AlpineComponent::make('filament-export-scheduler', __DIR__ . '/../resources/dist/components/filament-export-scheduler.js'),
//            Css::make('filament-export-scheduler-styles', __DIR__ . '/../resources/dist/filament-export-scheduler.css'),
//            Js::make('filament-export-scheduler-scripts', __DIR__ . '/../resources/dist/filament-export-scheduler.js'),
        ];
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

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            'create_export_scheduler_table',
        ];
    }
}
