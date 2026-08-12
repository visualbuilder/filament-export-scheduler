<?php

use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Enums\Width;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource;
use Visualbuilder\ExportScheduler\Mail\ExportReady;
use Visualbuilder\ExportScheduler\Notifications\ScheduledExportCompleteNotification;
use Visualbuilder\ExportScheduler\Support\ReportUserResolver;
use Visualbuilder\ExportScheduler\Support\VisibilityBypass;

return [

    /**
     * Filament resources to register
     */
    'resources' => [
        CustomReportResource::class,
    ],

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
     * The report viewer loads the whole result set so it can search and sort across every
     * page, including columns that are not database columns. Set a number here to cap how
     * many rows are loaded on very large reports. Null loads all of them.
     */
    'viewer_max_rows' => null,

    /**
     * Roles allowed to create SQL query reports.
     * Uses Spatie Permission hasRole() check.
     * Set to empty array to allow all users.
     */
    'sql_query_roles' => ['Developer'],

    /**
     * Admin Panel Navigation
     */
    'navigation' => [
        'enabled' => true,
        'sort' => 100,
        'label' => 'Custom Report',
        'plural_label' => 'Custom Reports',
        'icon' => 'heroicon-o-document-chart-bar',
        'group' => 'Reports',
        'cluster' => false,
        'position' => SubNavigationPosition::Top,

        /**
         * Width of the create, edit and delete modals in the schedules
         * relation manager. The schedule form is wide — frequency, recipient,
         * cc and overrides — so it needs more room than a Filament default.
         */
        'modal_width' => Width::FiveExtraLarge,
    ],

    /**
     * How the package reads a user's id, display label and email address.
     *
     * Bind your own implementation of ResolvesReportUsers here if the per-model
     * attributes below are not enough — e.g. "Surname, Forename (Dept)", or an
     * address that depends on a per-user preference.
     */
    'user_resolver' => ReportUserResolver::class,

    /**
     * Determines which users can bypass visibility restrictions and see all reports
     * and schedules. Bind your own implementation to control who gets admin-like access.
     * Default allows no one to bypass.
     */
    'visibility_bypass' => VisibilityBypass::class,

    /**
     * Automatic Recipients — reruns a report once per user found in the data and
     * sends each of them only their own rows.
     *
     * Hidden from the schedule form by default. Schedules that already have it
     * enabled keep fanning out regardless of this flag; it only governs whether
     * the fields can be reached in the UI.
     */
    'dynamic_recipients' => false,

    /**
     * Which authenticatable models may receive exports, and be given visibility
     * of a report.
     *
     * Only `model` is required. `title_attribute` and `email_attribute` each
     * default to 'email' independently — email_attribute deliberately does not
     * inherit title_attribute, so a model labelled by a name is never mailed at
     * that name. Both are read with data_get(), so 'contact.email' works.
     */
    'user_models' => [
        [
            /**
             * Change this to your own model, maybe \App\Models\User::class
             */
            'model' => \Visualbuilder\ExportScheduler\Tests\Models\User::class,
            'title_attribute' => 'email',
            // 'email_attribute' => 'email',
            // 'model_label' => 'Users',
        ],
    ],

];
