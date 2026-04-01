<?php

namespace Visualbuilder\ExportScheduler\Tests;

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
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Visualbuilder\ExportScheduler\ExportSchedulerServiceProvider;
use Visualbuilder\ExportScheduler\Tests\Models\User;
use Visualbuilder\ExportScheduler\Tests\Traits\CustomRefreshDatabase;
use Livewire\Mechanisms\DataStore;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Facades\View;

class TestCase extends Orchestra
{
    use CustomRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(
            User::create([
                'email' => 'admin@domain.com',
                'name' => 'Admin',
                'password' => 'password',
            ])
        );

        View::share('errors', new ViewErrorBag);
        $dataStore = app(DataStore::class);
        app()->instance(DataStore::class, $dataStore);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    protected function getPackageProviders($app)
    {
        return [
            ActionsServiceProvider::class,
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
