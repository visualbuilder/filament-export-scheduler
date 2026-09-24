# Changelog

All notable changes to `filament-export-scheduler` will be documented in this file.

## 6.2.1 - 2026-09-24

### Fixed
- A failed scheduled run is no longer recorded as successful. `export:run` used to set `last_successful_run_at` whatever the run's outcome; now a failed run keeps the previous value (or leaves it empty if the schedule has never succeeded), still records `last_run_at`, still advances `next_run_at` so a broken report is not retried every minute, and logs `Export failed` with the schedule id. The **Last Successful Run** column on a report's schedules now shows the last real success.
- **Run now** no longer shows the "export started" notification when the run fails. It shows the "Report could not be run" notification instead, as **Download** already did.

### Changed
- The stale-column notifications no longer assume they came from the **Download** button: the text no longer mentions downloading ("Then try again." / "were left out"), and the translation keys are named after the condition. If you published and overrode them in 6.2.0, rename your keys:
  - `download_stale_columns_title` → `stale_columns_title`
  - `download_stale_columns_body` → `stale_columns_body`
  - `download_partly_stale_columns_title` → `partly_stale_columns_title`
  - `download_partly_stale_columns_body` → `partly_stale_columns_body`
- The columns listed in the "Some columns were left out" notification are shown in italics.
- Both stale-column notifications stay on screen for 15 seconds instead of Filament's default 6, so the steps for fixing the columns can be read.

### Development
- PHPStan upgraded to 2.x (`phpstan/phpstan` `^2.1`, `phpstan/phpstan-deprecation-rules` `^2.0`, `phpstan/phpstan-phpunit` `^2.0`). PHPStan 1.x could not read Symfony Console 8, which Laravel 13 installs, and reported inherited command constants such as `Command::SUCCESS` as undefined.
- An owner-only visibility test no longer depends on test order under Livewire 4.

## 6.2.0 - 2026-09-24

### Added
- `CustomReport::getExportableColumnMap()`: the saved columns as a `[name => label]` map, limited to those the exporter still defines, with an optional fallback to the exporter's columns when none remain.
- `CustomReport::getStaleColumns()` and `CustomReport::hasOnlyStaleColumns()`: the saved columns the exporter no longer defines, and whether every saved column is one of them.

### Fixed
- A scheduled run no longer exports zero rows when a saved column was renamed or removed on the exporter. The stale column is left out and a warning is logged with the report, exporter, stale columns and schedule. The saved columns are not changed, since an exporter can define columns by context and a name missing from one run may be valid in another.

### Changed
- Scheduled runs never fall back to the exporter's columns, so a recipient is never sent columns nobody chose for them. A run with no columns to export (no saved columns, or none that still exist on the exporter) still sends an empty export, and now logs an error saying so.
- When none of a report's saved columns exist on the exporter any more, the report viewer shows the exporter's columns instead of an empty table.
- The report viewer now logs a warning when it leaves out a stale column, at most once an hour per report and set of stale columns. If the cache is unavailable the warning is logged anyway rather than breaking the viewer.
- The **Download** button no longer produces an empty file when none of the report's saved columns exist on the exporter. It shows a "Report columns need updating" notification instead. When only some are stale, the download goes ahead and a warning names the columns that were left out. Both notifications tell the user to remove the old columns from **Columns to include in report** and add them again from **Available Columns**.

## 6.1.0 - 2026-09-14

### Added
- `ScheduledReport::$schedule_summary` accessor: the schedule in one line of words, e.g. `Daily at 19:00`, `Weekly on Monday at 09:00`, `Monthly on the last day at 09:00`, `Cron 0 9 * * *`. The timezone is appended only when it differs from the application's.
- "Schedule" column on the custom reports list, listing every schedule for the report in words with disabled ones marked, and a `Not scheduled` placeholder. Finding which report fires at a given time no longer means opening each report.
- "Schedule" column on the schedules tab of a report, beside the frequency badge.

## 6.0.3 - 2026-08-18

### Added
- `HasLinkedColumns` contract. An exporter can implement it and define `getColumnLinks(): array`, mapping a column name to a resolver `callable(Model $record): ?string`. The report viewer calls the resolver for each row of a linked column and, when it returns a URL, renders that cell as a link through to the record — an "Order ID" column linking to the order itself, an "End User" column linking to the user a relation resolves to.

### Changed
- **Breaking Change**: Schedule-level `date_range` field is removed entirely. All schedules now inherit the report's `date_range`; overrides are no longer supported. This simplifies the schedule form and prevents confusion between report and schedule date ranges. See migration `2026_08_18_000001_consolidate_report_defaults_to_schedules.php`.
- **Breaking Change**: Consolidated multiple report-default migrations into a single migration step. The individual migrations (`create_custom_reports_table`, etc.) are replaced by one unified approach.
- Report defaults for `date_range` and `formats` are now editable on the report itself, and all schedules inherit them. Previously, per-schedule overrides were possible; now they are not.
- Ad hoc downloads (from the report viewer's **Download** button) now send completion notifications to the requester, matching the behaviour of scheduled exports.
- The report viewer's free-text search now only matches against the columns actually shown, so a column's resolved link URL can never itself be matched as if it were displayed text.

### Notes
- Fully opt-in `HasLinkedColumns`: exporters that don't implement it behave exactly as before. SQL query reports are unaffected.
- See the README section *Make columns clickable to their records* for usage.

### Migration

For applications upgrading from 6.0.2:

- Run the new consolidation migration: `php artisan migrate`. This removes the `date_range` column from `ScheduledReport`.
- If you had any logic depending on per-schedule `date_range` overrides, you will need to refactor it to read from the report's `date_range` instead.

## 6.0.2 - 2026-08-12

### Removed
- **Breaking Change**: Removed the standalone `ScheduledReportResource` navigation item. Schedules are now managed exclusively inline via the `SchedulesRelationManager` on the `CustomReportResource` edit page. The `ScheduledReport` model and database table remain unchanged.

### Changed
- Navigation collapsed from two separate items (`CustomReports` / `Report Schedules`) to a single `CustomReports` item. Config `navigation.schedules.*` keys are no longer used; only `navigation.enabled`, `navigation.*` (for the resource), and `navigation.modal_width` (for schedule modals) are meaningful.
- `ExportSchedulerPlugin` simplified: removed `enableReportNavigation()` / `enableScheduleNavigation()` / `shouldRegisterReportNavigation()` / `shouldRegisterScheduleNavigation()` methods. Use `enableNavigation()` / `shouldRegisterNavigation()` to control visibility of the single remaining navigation item.

## 6.0.0 - 2026-08-10

### Added
- **Breaking Change**: Split `ExportSchedule` into `CustomReport` (definition) and `ScheduledReport` (delivery layer)
- Report visibility and sharing: Owner, User Type, or Named Users with view-only access
- `ResolvesReportUsers` contract for flexible user identity resolution with fallback chain: `HasExportReportIdentity` → config attributes with dot-notation → fallback label
- `ReportVisibility` enum for three-mode visibility control
- Automatic migration of existing `export_schedules` to new `custom_reports` + `scheduled_reports` tables with zero data loss
- `ScheduledReport` model with inheritance pattern for `date_range` and `formats` (report defaults, schedule overrides)
- `CustomReportResource` and `ScheduledReportResource` replacing monolithic `ExportScheduleResource`
- `SchedulesRelationManager` for inline schedule management on report edit page
- `dynamic_recipients` config flag to hide Automatic Recipients UI while preserving existing behavior
- `BypassesReportVisibility` contract, bound from the `visibility_bypass` config key. A user it returns `true` for is treated as the owner of every report and schedule: they see all reports whatever the visibility mode, and may edit, delete and run reports and schedules they do not own. The default implementation, `VisibilityBypass`, grants this to nobody, so behaviour is unchanged until you bind your own

### Changed
- `ScheduledExporter` constructor now takes `(CustomReport, ?ScheduledReport)` instead of `ExportSchedule`
- `ScheduledExportCompletion` constructor updated to match
- `ScheduledExportCompleteNotification` and `ExportReady` mail signature updated
- Email template variables now reference `$report` and `$schedule` separately
- User resolution throughout the package now uses container-bound `ResolvesReportUsers`
- Navigation split into "Custom Reports" and "Report Schedules" with separate config blocks

### Removed
- `ExportSchedule` model (replaced by `CustomReport` + `ScheduledReport`)
- `ExportScheduleResource` (replaced by `CustomReportResource` + `ScheduledReportResource`)
- Database table `export_schedules` (migrated to `custom_reports` + `scheduled_reports`)

### Fixed
- CC recipients whose type changes now have their list cleared automatically to prevent id mismatches
- Visibility lists cleared when visibility mode or type changes, preventing stale ids from being checked
- Form fields clear visibly as the user changes types, mirroring model-level guards
- UUID and non-numeric user keys no longer silently dropped in cc fan-out

### Documentation
- README restructured around the report/schedule split, and the previously undocumented 6.0 features written up: SQL query reports and the `sql_query_roles` gate, report visibility and sharing, user identity resolution, per-schedule date range and format overrides, per-resource navigation gating on the plugin, and a table of the four changed constructor signatures
- README config sample replaced. It still registered the removed `ExportScheduleResource` and showed the old flat `navigation` block, so following it produced a broken install
- README seeder command corrected to `CustomReportSeeder`, preceded by the `export-scheduler-seeders` publish
- README trait name corrected to `InteractsWithExportSchedulerFilter`, and the section now shows importing it rather than pasting its source
- Removed a documented "since X days/weeks/months/years ago" custom date period that the package has never implemented, and documented the `next_7_days` / `next_30_days` / `next_60_days` / `next_90_days` presets, which were absent
- Fixed an unclosed README code fence that suppressed rendering from the Testing section onward
- Dropped five screenshots showing the pre-6.0 UI, including the removed **Schedule** tab
- UPGRADE.md: corrected the legacy column name to `formats`, and the verification steps, which asked you to count rows in `export_schedules` after the migration had already dropped it. Documented that the migration no-ops on fresh installs, drops the old table when it completes, and is reversible

### Migration
- See `UPGRADE.md` for a detailed migration guide
- Run `php artisan vendor:publish --tag=export-scheduler-migrations` to publish migrations, then `php artisan migrate`
- No data loss; all existing schedules continue running after upgrade

## 5.2.0 - 2026-08-07

### Added
- In-system report viewer. Opening a saved report shows its live results in the panel: full width, 50 rows per page, sortable column headers, and a search box matching any field across every page.
- Download action on both the report viewer and the schedules list. It runs the report through the normal queued export pipeline and returns the file to whoever asked for it, in CSV or XLSX.
- `viewer_max_rows` config option, capping how many rows the viewer loads on very large reports. Defaults to `null` (no cap). The export itself is never capped.

### Changed
- Viewer cell values now come from the exporter's own `ExportColumn` formatting, so the viewer, the download and the emailed scheduled export all show the same values.
- Searching and sorting in the viewer are done in PHP over the whole result set rather than in the database. A report's columns are not necessarily database columns: exporter reports can name morph relations, relationship aggregates and accessors, and SQL query reports return whatever expressions and aliases the author wrote.
- `ScheduledExportCompletion::__construct()` accepts a third `$isAdHoc` argument, defaulting to `false`. An ad hoc download notifies only the requester and is never suppressed by `send_empty_report`.
- `ViewExportSchedule` was rewritten around a materialised result set. Its protected methods `getSqlQueryRecords()`, `getSqlQuerySubquery()`, `hasUsableExporter()`, `getExporterColumns()`, `getSqlQueryColumns()` and `makeEmptyPaginator()` have been replaced. Only relevant if you were extending the page, which was introduced in 5.1.1.

### Fixed
- XLSX files were never produced. `formats` is cast to `array`, so it holds strings after a database round trip, and `in_array(ExportFormat::Xlsx, $formats)` never matched an enum. `CreateXlsxFile` was silently never chained.
- XLSX on a SQL query report would fatal, because Filament's `CreateXlsxFile` resolves an exporter class in its constructor and SQL query reports have none. Added `CreateSqlQueryXlsxFile`.
- A SQL query report matching no rows wrote no files at all, so downloading it failed with "Unable to read file from location". Column names now come from the PDO result set metadata, so headers are written even with no rows, and an empty report is still downloadable.
- The report viewer showed a blank table with no column headings when a SQL query report returned no rows, which was indistinguishable from an error.
- A relation column whose name contains a dot, such as `owner.created_at`, rendered blank in the viewer, because the name was treated as a nested path rather than the row's key.
- `ExportReady` mailed every recipient's notification to the schedule owner, and always linked to the CSV. It now sends to the notifiable and links to whichever file was actually written.
- `ExportSchedule::isSyncQueue()` asked a non-existent exporter class for its queue, so merely mounting the Run action on a SQL query report threw a `TypeError`. `isCurrentUserOwner()` dereferenced a null owner on the same path.

## 5.0.0 - 2026-03-31

### Added
- Filament 5.x compatibility
- Livewire 4.x compatibility
- Pest 4.x compatibility
- PHPUnit 12.x compatibility

### Changed
- Updated `filament/filament` dependency from `^4.0` to `^5.0`
- Updated `pestphp/pest` dependency from `^3.0` to `^4.0`
- Updated `pestphp/pest-plugin-laravel` dependency from `^3.0` to `^4.0`
- Updated `pestphp/pest-plugin-livewire` dependency from `^3.0` to `^4.0`

### Notes
- All 103 tests pass with 352 assertions
- Zero breaking changes required - smooth upgrade path
- Schemas namespace remains fully compatible with Filament 5

## 1.0.0 - 202X-XX-XX

- initial release
