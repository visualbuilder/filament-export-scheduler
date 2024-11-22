<?php

namespace VisualBuilder\ExportScheduler\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use VisualBuilder\ExportScheduler\ExportSchedulerServiceProvider;
use VisualBuilder\ExportScheduler\Tests\Models\User;

class TestCase extends Orchestra
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn(string $modelName) => 'VisualBuilder\\ExportScheduler\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->actingAs(
            User::create(['email' => 'admin@domain.com', 'name' => 'Admin', 'password' => 'password'])
        );

    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__ . '/migrations');
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Retrieve the version of Laravel.
        $laravelVersion = app()->version();

        // Laravel 11 and higher
        if (version_compare($laravelVersion, '11.0', '>=')) {
            Artisan::call('make:queue-batches-table');
            Artisan::call('make:notifications-table');
        }
        // Laravel 10
        else if (version_compare($laravelVersion, '10.0', '>=')) {
            Artisan::call('queue:batches-table');
            Artisan::call('notifications:table');
        }

        Artisan::call('vendor:publish --tag=filament-actions-migrations');
        Artisan::call('vendor:publish --tag=filament-notifications-migrations');


    }

    protected function getPackageProviders($app)
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            AdminPanelProvider::class,
            ExportSchedulerServiceProvider::class,
        ];
    }
}
