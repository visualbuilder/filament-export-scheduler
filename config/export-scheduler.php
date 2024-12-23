<?php

use VisualBuilder\ExportScheduler\Filament\Resources\ExportScheduleResource;
use VisualBuilder\ExportScheduler\Mail\ExportReady;
use VisualBuilder\ExportScheduler\Notifications\ScheduledExportCompleteNotification;

return [

    /**
     * Which Schedule Definition Resource to Load if you want to extend put your own resource here
     */
    'resources'            => [ExportScheduleResource::class],

    /**
     * The success Notification and Mailable to use
     */
    'notification'         => ScheduledExportCompleteNotification::class,
    'mailable'             => ExportReady::class,

    /**
     * Allow users to choose from Exporters in these directories
     */
    'exporter_directories' => [
        'App\Filament\Exporters',
    ],

    'file_disk'   => 'local',
    /**
     * Admin Panel Navigation
     * See also Plugin options
     */
    'navigation'  => [
        'enabled'      => true,
        'sort'         => 100,
        'label'        => 'Scheduled Report',
        'plural_label' => 'Scheduled Reports',
        'icon'         => 'heroicon-o-paper-airplane',
        'group'        => 'Reports',
        'cluster'      => false,
        'position'     => \Filament\Pages\SubNavigationPosition::Top,
    ],

    /**
     * Which authenticatable models should be allowed to receive exports
     * What you set here will define what appears on the user dropdown list
     */
    'user_models' => [

        [
            /**
             * Change this to your own model maybe \App\Models\User::class
             *
             */
            'model'           => \VisualBuilder\ExportScheduler\Tests\Models\User::class,
            'title_attribute' => 'email',
        ],
    ],

];
