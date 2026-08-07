# Changelog

All notable changes to `filament-export-scheduler` will be documented in this file.

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
