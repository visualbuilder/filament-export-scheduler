# Upgrade Guide

## 6.0 → 6.0.2: Consolidation of Schedule Management

Version 6.0 introduced the standalone `ScheduledReportResource` as a separate navigation item for managing schedules globally. Version 6.0.2 removes it, consolidating all schedule management into the inline `SchedulesRelationManager` on each report's edit page.

### Breaking Changes

#### Navigation

The standalone "Report Schedules" navigation item is no longer registered. Schedules are now managed exclusively inline when editing a report.

**If you subclassed `ScheduledReportResource` or customized its navigation:**
- Delete any subclass of `ScheduledReportResource`.
- Remove its import from your plugin registration or config.
- Schedule management is now done via the `SchedulesRelationManager` on `CustomReportResource`.

#### Configuration

Your `config/export-scheduler.php` must change:

**Before:**
```php
'resources' => [
    CustomReportResource::class,
    ScheduledReportResource::class,
],

'navigation' => [
    'reports' => [
        'enabled' => true,
        'sort' => 100,
        'label' => 'Custom Report',
        'plural_label' => 'Custom Reports',
        'icon' => 'heroicon-o-document-chart-bar',
        'group' => 'Reports',
        // ...
    ],
    'schedules' => [
        'enabled' => true,
        'sort' => 101,
        'label' => 'Report Schedule',
        'plural_label' => 'Report Schedules',
        'icon' => 'heroicon-o-paper-airplane',
        'group' => 'Reports',
        'modal_width' => Width::FiveExtraLarge,
        // ...
    ],
],
```

**After:**
```php
'resources' => [
    CustomReportResource::class,
],

'navigation' => [
    'enabled' => true,
    'sort' => 100,
    'label' => 'Custom Report',
    'plural_label' => 'Custom Reports',
    'icon' => 'heroicon-o-document-chart-bar',
    'group' => 'Reports',
    'modal_width' => Width::FiveExtraLarge,
    // ...
],
```

#### Plugin API

The `ExportSchedulerPlugin` no longer supports per-resource navigation control:

**Before:**
```php
ExportSchedulerPlugin::make()
    ->enableNavigation(fn () => auth()->user()->can('viewReports'))
    ->enableScheduleNavigation(fn () => auth()->user()->can('sendReports'))
```

**After:**
```php
ExportSchedulerPlugin::make()
    ->enableNavigation(fn () => auth()->user()->can('viewReports'))
    // Use a single enableNavigation() to gate both reports and inline schedule management
```

The following methods are removed:
- `enableReportNavigation()`
- `enableScheduleNavigation()`
- `shouldRegisterReportNavigation()`
- `shouldRegisterScheduleNavigation()`

### Migration Checklist

- [ ] Remove `ScheduledReportResource` from `config/export-scheduler.php` resources array
- [ ] Collapse the two-block `navigation` config into one (reports + schedules → single block with `modal_width`)
- [ ] Update config keys from `navigation.reports.*` to `navigation.*`
- [ ] Delete any subclass of `ScheduledReportResource` in your application
- [ ] Replace any `enableScheduleNavigation()` calls with a single `enableNavigation()`
- [ ] Verify no tests reference `ScheduledReportResource` or its pages directly
- [ ] Test schedule creation/editing inline on the report edit page

---

## 5.x to 6.0.0: Report/Schedule Split

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
- `next_run_at`, `last_run_at` and `last_successful_run_at` are copied verbatim, so nothing fires early, late or twice.
- Once the copy completes, `export_schedules` is dropped.

No data is lost. All existing schedules continue running after the upgrade, with identical timing and recipients.

The migration is a no-op on a fresh install, where there is no `export_schedules` table to read.

It is reversible: `php artisan migrate:rollback` recreates `export_schedules` and repopulates it by
joining the two new tables back together. A schedule's override wins over the report default on the
way back, the inverse of how `up()` split them. Note that reports sharing one definition across
several schedules become one legacy row per schedule, and visibility settings have nowhere to go.

## Model Changes

### Models Renamed/Replaced

- `ExportSchedule` → `CustomReport` + `ScheduledReport`
  - `CustomReport` holds: `name`, `report_type`, `exporter`, `sql_query`, `columns`, `filters`, `date_range`, `formats`, `owner_*`, visibility fields
  - `ScheduledReport` holds: `custom_report_id`, all timing fields, `recipient_*`, `cc`, delivery options

### Column Changes

- `ExportSchedule.owner_type` / `owner_id` → `ScheduledReport.recipient_type` / `recipient_id`  
  (The `owner` morph on `CustomReport` is the report's creator, not the recipient)
- `ExportSchedule.formats` → `CustomReport.formats`, with `ScheduledReport.formats` as a nullable override. Null on the schedule means inherit from the report.
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

Update your `config/export-scheduler.php`. The keys that changed shape:

```php
// Was: 'resources' => [ExportScheduleResource::class],
'resources' => [
    CustomReportResource::class,
    ScheduledReportResource::class,
],

// Was: one flat block of nav settings. Now one block per resource.
// See the README for the full annotated block, including the new
// 'modal_width' controlling the schedule modals in the relation manager.
'navigation' => [
    'reports' => [/* enabled, sort, label, plural_label, icon, group, cluster, position */],
    'schedules' => [/* the same, plus 'modal_width' */],
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
use Visualbuilder\ExportScheduler\Contracts\HasExportReportIdentity;

class Contact extends Model implements HasExportReportIdentity
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

1. **Before migrating**, note two things, because the migration drops `export_schedules` when it
   finishes and you cannot go back for them afterwards:
   `SELECT COUNT(*) FROM export_schedules` and `SELECT id, next_run_at FROM export_schedules ORDER BY id`
2. Run migrations: `php artisan migrate`
3. Check that the `custom_reports` and `scheduled_reports` tables exist
4. Verify the counts match the number you noted: `SELECT COUNT(*) FROM custom_reports` and
   `SELECT COUNT(*) FROM scheduled_reports` should both equal it
5. Confirm the timings are unchanged: `SELECT next_run_at FROM scheduled_reports ORDER BY id`
   against what you noted in step 1
6. Load the admin panel and verify both nav items appear under "Reports"
7. Click into a migrated report and confirm the Schedules relation manager shows its schedule
8. Run a test schedule: `php artisan export:run`

Rehearse on a copy of production first. `migrate:rollback` does restore `export_schedules`, but a
restore is not the same as never having left.

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

## Further Reading

The [README](README.md) documents the 6.0 features in full, rather than just the migration path:

- *Reports and schedules are separate things* — the model the rest of the release follows from
- *Share reports with other users* — the three visibility modes and what each one grants
- *How the package identifies your users* — the resolver's fallback chain, and how to override it
  per model or replace it wholesale
- *Choose who receives it* — recipients, cc, and the per-schedule date range and format overrides
- *Extending the package* — the constructor signatures collected in one table
