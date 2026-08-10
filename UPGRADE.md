# Upgrade Guide: 5.x to 6.0.0

Version 6.0.0 is a **breaking change** that restructures how reports and schedules work. This guide covers the migration path.

## Overview

The `ExportSchedule` model has been split into two separate models:

- **`CustomReport`** — the report definition (exporter, columns, filters, date range)
- **`ScheduledReport`** — the optional delivery layer (frequency, recipient, cc)

This allows the same report to be delivered on multiple schedules, or not delivered at all (for use in the in-system viewer only).

## Installation & Migration

To upgrade, publish the new migrations and run them:

```bash
php artisan vendor:publish --tag=export-scheduler-migrations
php artisan migrate
```

This will create the new `custom_reports` and `scheduled_reports` tables, and automatically migrate your existing `export_schedules` data.

## Database Migration

The existing `export_schedules` table is automatically migrated to the new structure via migration `2026_08_10_000003_migrate_export_schedules_to_custom_reports`:

- Each `ExportSchedule` row becomes one `CustomReport` row (copying the report definition) and one `ScheduledReport` row (copying the delivery configuration).
- `CustomReport` rows inherit the `owner_type` and `owner_id` from the original `ExportSchedule`, and all get `visibility = 'owner'` so only the original owner can see them.
- `ScheduledReport` rows have their `recipient_type` and `recipient_id` set from the original `ExportSchedule.owner_type` and `owner_id` (the delivery recipient, not the report creator).
- `ScheduledReport` rows inherit all timing fields from the original schedule, with `date_range` and `formats` left **null** to inherit from the report.

No data is lost. All existing schedules continue running after the upgrade, with identical timing and recipients.

## Model Changes

### Models Renamed/Replaced

- `ExportSchedule` → `CustomReport` + `ScheduledReport`
  - `CustomReport` holds: `name`, `report_type`, `exporter`, `sql_query`, `columns`, `filters`, `date_range`, `formats`, `owner_*`, visibility fields
  - `ScheduledReport` holds: `custom_report_id`, all timing fields, `recipient_*`, `cc`, delivery options

### Column Changes

- `ExportSchedule.owner_type` / `owner_id` → `ScheduledReport.recipient_type` / `recipient_id`  
  (The `owner` morph on `CustomReport` is the report's creator, not the recipient)
- `ExportSchedule.format` → `ScheduledReport.formats` (array of overrides) with fallback to `CustomReport.formats`
- `ExportSchedule.date_range` → both tables (report defaults, schedule overrides)

## API Changes

### Constructor Changes

**Before:**
```php
new ScheduledExporter($exportSchedule)
```

**After:**
```php
new ScheduledExporter($report, $schedule)
// Or for ad-hoc runs:
new ScheduledExporter($report)  // $schedule is optional
```

### Job Signature Changes

**Before:**
```php
dispatch(new ScheduledExportCompletion($export, $exportSchedule));
```

**After:**
```php
dispatch(new ScheduledExportCompletion($export, $report, $schedule, $isAdHoc = false));
```

### Notification Signature Changes

**Before:**
```php
$user->notify(new ScheduledExportCompleteNotification($export, $exportSchedule));
```

**After:**
```php
$user->notify(new ScheduledExportCompleteNotification($export, $report, $schedule));
```

### Mail Signature Changes

**Before:**
```php
public function __construct(public $notifiable, public Export $export, public ExportSchedule $exportSchedule)
```

**After:**
```php
public function __construct(public Export $export, public CustomReport $report, public ?ScheduledReport $schedule = null)
```

## Filament Resources

### Resources Changed

- `ExportScheduleResource` → `CustomReportResource` + `ScheduledReportResource`
- Navigation now has two separate menu items under "Reports" group:
  - "Custom Reports" (manage report definitions)
  - "Report Schedules" (manage delivery schedules)

### Configuration

Update your `config/export-scheduler.php`:

```php
'resources' => [
    CustomReportResource::class,
    ScheduledReportResource::class,
],

'navigation' => [
    'reports' => [/* config for custom reports nav */],
    'schedules' => [/* config for report schedules nav */],
],

// New: configure how users are identified
'user_models' => [
    [
        'model' => \App\Models\User::class,
        'title_attribute' => 'full_name',  // or 'email'
        'email_attribute' => 'email_address',  // or 'contact.email' with relations
        'model_label' => 'Users',  // optional, defaults to class_basename
    ],
],

// New: swap out user resolution if needed
'user_resolver' => \Visualbuilder\ExportScheduler\Support\ReportUserResolver::class,

// New: Automatic Recipients is now opt-in
'dynamic_recipients' => false,  // set to true to re-enable the dynamic_owner_* fields
```

## User Identity Resolution

The package no longer assumes users have `email`, `id`, or `name` columns. All user lookups go through a configurable resolver.

### Basic Usage

Define users in config with `title_attribute` and `email_attribute`:

```php
'user_models' => [
    [
        'model' => \App\Models\Contact::class,
        'title_attribute' => 'contact_name',
        'email_attribute' => 'email_addr',
    ],
],
```

### Advanced: Relations and Custom Logic

Dot-notation works for relations:

```php
'email_attribute' => 'person.email_address',  // loads person relation automatically
```

Per-model override:

```php
class Contact implements \Visualbuilder\ExportScheduler\Contracts\HasExportReportIdentity
{
    public function getExportReportLabel(): string
    {
        return "{$this->last_name}, {$this->first_name}";
    }

    public function getExportReportEmail(): ?string
    {
        return $this->email ?? $this->alternate_email;
    }
}
```

Or swap the whole resolver:

```php
// In a service provider
$this->app->bind(
    \Visualbuilder\ExportScheduler\Contracts\ResolvesReportUsers::class,
    \App\Services\CustomReportUserResolver::class,
);
```

## Automatic Recipients (Deprecated)

The `dynamic_owner_enabled` and `dynamic_owner_attribute` fields are **hidden from the UI** by default.

- Existing schedules with `dynamic_owner_enabled = true` continue to work
- New schedules cannot set it through the form (unless you set `'dynamic_recipients' => true` in config)
- To re-enable: set `'dynamic_recipients' => true` and the fields reappear

This allows for a gradual migration away from the feature.

## Report Visibility

Reports now support sharing:

- **Owner** (default) — only the creator can view/edit
- **User Type** — all users of a specific class can view (but not edit)
- **Named Users** — specific users can view (but not edit)

Viewing includes in-system viewer and CSV/XLSX downloads. Editing, deleting, and schedule management remain owner-only regardless of visibility.

## Publishing Updated Seeders

If you published the seeder, replace it:

```bash
php artisan vendor:publish --tag=export-scheduler-seeders --force
```

Or update manually from `CustomReportSeeder` instead of `ExportScheduleSeeder`.

## Testing Your Upgrade

1. Run migrations: `php artisan migrate`
2. Check that `custom_reports` and `scheduled_reports` tables exist
3. Verify row counts match: `SELECT COUNT(*) FROM custom_reports` should equal old `export_schedules` count
4. Confirm next_run_at values are unchanged: `SELECT next_run_at FROM scheduled_reports`
5. Load the admin panel and verify both nav items appear under "Reports"
6. Click into a migrated report and confirm the Schedule relation manager shows the schedule
7. Run a test schedule: `php artisan export:run`

## Troubleshooting

### "Custom Report not found" when running schedules

The migration copies `export_schedules` data, but if it fails:

1. Check the migration output: `php artisan migrate --step`
2. Verify the `custom_reports` and `scheduled_reports` tables were created
3. If stuck, run the migration on a backup before retrying on production

### Mail sending errors

If you get "Cannot send export email: user has no configured email address":

1. Verify your `user_models` config has the right `email_attribute`
2. Check that the attribute exists on your user model
3. For relations, ensure the related model exists in the database

### Old app code references ExportSchedule

Search your app for `ExportSchedule` and update:

- Model imports → use `CustomReport` or `ScheduledReport`
- Constructors → pass both models to `ScheduledExporter`
- Notifications → pass both models
- Mails → pass both models
- Resources → use `CustomReportResource` and `ScheduledReportResource`
- Configs → update the resources list
