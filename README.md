# Configure scheduled routines for filament exporters

[![Latest Version on Packagist](https://img.shields.io/packagist/v/visualbuilder/filament-export-scheduler.svg?style=flat-square)](https://packagist.org/packages/visualbuilder/filament-export-scheduler)
[![run-tests](https://github.com/visualbuilder/filament-export-scheduler/actions/workflows/run-tests.yml/badge.svg)](https://github.com/visualbuilder/filament-export-scheduler/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/visualbuilder/filament-export-scheduler.svg?style=flat-square)](https://packagist.org/packages/visualbuilder/filament-export-scheduler)
![GitHub commit activity](https://img.shields.io/github/commit-activity/w/visualbuilder/filament-export-scheduler)

Email automated exports on a defined schedule - keep the management happy with timely reporting and minimise effort.

![Export Schedules pack shot](https://raw.githubusercontent.com/visualbuilder/filament-export-scheduler/3.x/media/social-card.jpg)

## Empower users to create their own reports

![List Schedules](https://raw.githubusercontent.com/visualbuilder/filament-export-scheduler/3.x/media/reports-index.png)

## Any Filament Exporter can be used as the starting point

- Exporters will be discovered in ```App\Filament\Exporters``` or add more locations in the config
- Send Exports to other users
- To ensure data security only system users can receive reports

![Setup Schedules](https://raw.githubusercontent.com/visualbuilder/filament-export-scheduler/3.x/media/edit-export.png)


## Easy Frequency Selection

- Choose from preset frequencies
  - daily
  - weekly
  - monthly
  - quarterly
  - half yearly
  - yearly
- Or use a custom Cron for non standard schedules

![Setup Schedules](https://raw.githubusercontent.com/visualbuilder/filament-export-scheduler/3.x/media/cron.png)


## Customise query data range

- Choose from preset query ranges
  - today
  - yesterday
  - last 7 days
  - last week
  - last 30 days
  - last month
  - this month
  - last quarter
  - this year
  - last year
- Custom date field if not created_at can be set in the Exporter

## Users can choose which columns to include

- Currently all columns must be defined in the exporter
- Maybe will add ability to add user definable columns
- All Column Formatting options set in the exporter

![Edit Columns](https://raw.githubusercontent.com/visualbuilder/filament-export-scheduler/3.x/media/edit-columns.png)

## Attractive HTML Email templates

 - Default HTML email template included
 - Or works well with [Visual Builder Email Templates](https://github.com/visualbuilder/email-templates "Other Free Package")  - if you want user editable emails
 - Custom Report Builder and Templates coming soon 

![Email](https://raw.githubusercontent.com/visualbuilder/filament-export-scheduler/3.x/media/vb-email.png)


## Installation

You can install the package via composer:

```bash
composer require visualbuilder/filament-export-scheduler
```

Copy views and migrations, run the migration

```bash
php artisan export-scheduler:install
```

Optionally seed an example schedule for the users table
This will export all Users on the 1st of every month.

```bash
php artisan db:seed --class=ExportScheduleSeeder
```

## Schedule Command in Laravel

To enable automatic sending you must add the console command to your scheduler.

### 1. Modify the Scheduler

Open `app\Console\Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('export:run')->everyMinute();
}
```

### 2. Check servers cron

Ensure your server is set up to run Laravel's scheduler by adding this cron entry

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### 3. Review the config file

Here you can 
- override the resource page
- customise the navigation menu
- set the disk to be used
- customise which notification and email template is used
- set which user classes can receive reports 

```php
return [

    /**
     * To customise the Export Schedule Resource put your own resource here
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
    
    /**
     * Where the exports should be stored local or s3 
     */
    'file_disk' => 'local',
    
    /**
     * Admin Panel Navigation
     * See also Plugin options
     */
    'navigation' => [
        'enabled'      => true,
        'sort'         => 100,
        'label'        => 'Scheduled Report',
        'plural_label' => 'Scheduled Reports',
        'icon'         => 'heroicon-o-paper-airplane',
        'group'        => 'Reports',
        'cluster'      => false,
        'position'     => \Filament\Pages\SubNavigationPosition::Top
    ],

    /**
     * Which authenticatable models should be allowed to receive exports
     * Will be used to populate the Owner picker 
     */
    'user_models' => [

        [
            'model' => \App\Models\User::class,
            'title_attribute' => 'email',
        ],
    ],
];
```
### 4. Ensure you have added the filament Export migrations
If you don't have an exports table you can add it with:-
```php
# Laravel 11 and higher
php artisan make:queue-batches-table
php artisan make:notifications-table
 
# Laravel 10
php artisan queue:batches-table
php artisan notifications:table

# All apps
php artisan vendor:publish --tag=filament-actions-migrations
```

Check the docs at: https://filamentphp.com/docs/3.x/actions/prebuilt-actions/export

#### Polymorphism - Using different user classes
This package uses this by default, please ensure your exports migration has this line:-
```php
 $table->morphs('user');
```
This will create the columns user_type and user_id to allow any user type to be associated with an export.

Note: This code has already been included in the package service provider so you don't need to include it.
```php
Export::polymorphicUserRelationship();
```



Add the plugin to your filament panel provider
```php
use VisualBuilder\ExportScheduler\ExportSchedulerPlugin;

public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->plugins([
                ExportSchedulerPlugin::make(),

```

Make some export classes with 

```bash
php artisan make:filament-exporter
```

## Testing

```bash
composer test
```

To prove the integrity of the system the test suite creates these schedules and simulates running the tasks every day for 4 years to ensure leap years are handled correctly.

| Test Name                       | Schedule Frequency | Cron Expression           | Expected Report Time                                               | Verification Method (Example)                                    |
| ------------------------------ |--------------------| ------------------------- | ---------------------------------------------------------------- | ----------------------------------------------------------------- |
| User Export Daily                | DAILY              | N/A                      | Every day at 3:00 AM                                               | Check for a new file/email at 3:00 AM each simulated day.       |
| User Export Weekly (Monday)      | WEEKLY             | N/A                      | Every Monday at 4:00 AM                                            | Check for a new file/email at 4:00 AM every Monday.               |
| User Export Monthly (Day 15)      | MONTHLY            | N/A                      | The 15th day of every month at 5:00 AM                            | Check for a new file/email at 5:00 AM on the 15th of each month. |
| User Export Monthly (Last Day)  | MONTHLY            | N/A                      | The last day of every month at 6:00 AM                           | Check for a new file/email at 6:00 AM on the last day of each month. |
| User Export Yearly              | YEARLY             | N/A                      | January 1st of every year at 7:00 AM                           | Check for a new file/email at 7:00 AM on January 1st.         |
| User Export Leap Year Test      | YEARLY            | N/A                      | February 29th (during leap years) at 10:00 AM       | Check for file/email on Feb 29th in leap years; verify handling in non-leap years. |
| User Export Quarterly (Jan 10)   | QUARTERLY          | N/A                      | January 10th, April 10th, July 10th, October 10th at 8:00 AM  | Check for a new file/email on these dates at 8:00 AM.     |
| User Export Half-Yearly (Jul 1) | HALF_YEARLY        | N/A                      | July 1st and January 1st of every year at 9:00 AM                 | Check for a new file/email on these dates at 9:00 AM.            |
| User Export Weekdays 10:30 AM   | CRON               | `30 10 * * 1-5`          | Every weekday (Monday-Friday) at 10:30 AM                        | Check for a new file/email at 10:30 AM each weekday.           |
| User Export Quarter Ends 2:00 AM | CRON               | `0 2 1 3,6,9,12 *`      | The 1st day of March, June, September, and December at 2:00 AM    | Check for a new file/email on these dates at 2:00 AM.            |

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Lee Evans](https://github.com/lee)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
