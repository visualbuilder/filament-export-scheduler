<?php

use Visualbuilder\ExportScheduler\Filament\Resources\ExportScheduleResource;
use Visualbuilder\ExportScheduler\Mail\ExportReady;
use Visualbuilder\ExportScheduler\Notifications\ScheduledExportCompleteNotification;

return [

    /**
     * Which Schedule Definition Resource to Load if you want to extend put your own resource here
     */
    'resources' => [ExportScheduleResource::class],

    /**
     * The success Notification and Mailable to use
     */
    'notification' => ScheduledExportCompleteNotification::class,
    'mailable' => ExportReady::class,

    /**
     * Allow users to choose from Exporters in these directories
     */
    'exporter_directories' => [
        'App\Filament\Exporters',
    ],

    'file_disk' => 'local',

    /**
     * Roles allowed to create SQL query reports.
     * Uses Spatie Permission hasRole() check.
     * Set to empty array to allow all users.
     */
    'sql_query_roles' => ['Developer'],

    /**
     * Admin Panel Navigation
     * See also Plugin options
     */
    'navigation' => [
        'enabled' => true,
        'sort' => 100,
        'label' => 'Scheduled Report',
        'plural_label' => 'Scheduled Reports',
        'icon' => 'heroicon-o-paper-airplane',
        'group' => 'Reports',
        'cluster' => false,
        'position' => class_exists(\Filament\Pages\Enums\SubNavigationPosition::class)
            ? \Filament\Pages\Enums\SubNavigationPosition::Top
            : \Filament\Pages\SubNavigationPosition::Top,
    ],

    /**
     * Which authenticatable models should be allowed to receive exports
     * What you set here will define what appears on the user dropdown list
     */
    'user_models' => [

        [
            /**
             * Change this to your own model maybe \App\Models\User::class
             */
            'model' => \Visualbuilder\ExportScheduler\Tests\Models\User::class,
            'title_attribute' => 'email',
        ],
    ],

];
