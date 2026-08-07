<?php

namespace Visualbuilder\ExportScheduler\Filament\Resources\ExportScheduleResource\Pages;

use Filament\Actions\EditAction;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Visualbuilder\ExportScheduler\Filament\Actions\DownloadExport;
use Visualbuilder\ExportScheduler\Filament\Actions\RunExport;
use Visualbuilder\ExportScheduler\Filament\Resources\ExportScheduleResource;
use Visualbuilder\ExportScheduler\Models\ExportSchedule;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;
use Visualbuilder\ExportScheduler\Support\SqlQueryResult;

/**
 * Shows the live results of a saved report, without running the export.
 *
 * Searching and sorting are done in PHP over the whole result set rather than in the
 * database. A report's columns are not necessarily database columns: exporter reports
 * can name morph relations, relationship aggregates and accessors, and SQL query reports
 * return whatever expressions and aliases the author wrote. None of those can be safely
 * pushed into an ORDER BY or WHERE, so the rows are materialised and filtered here,
 * which also means the search matches exactly what is displayed.
 */
class ViewExportSchedule extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = ExportScheduleResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;

    /**
     * The whole report, each row keyed by column name with its display value.
     */
    protected ?Collection $resultRows = null;

    /**
     * Column name => label, in report order.
     *
     * @var array<string, string>|null
     */
    protected ?array $resultColumns = null;

    protected bool $isTruncated = false;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    public function getBreadcrumb(): ?string
    {
        return __('export-scheduler::scheduler.preview');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DownloadExport::make('download'),
            RunExport::make('run export'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        // Resolve the rows up front so the truncation notice is known before the table is built.
        $this->getResultRows();

        return $table
            ->striped()
            ->searchable()
            ->searchPlaceholder(__('export-scheduler::scheduler.search_placeholder'))
            ->paginated([25, 50, 100, 200])
            ->defaultPaginationPageOption(50)
            ->modelLabel(__('export-scheduler::scheduler.preview_row'))
            ->description($this->isTruncated
                ? __('export-scheduler::scheduler.viewer_truncated', ['count' => $this->getMaxRows()])
                : null)
            ->emptyStateHeading(__('export-scheduler::scheduler.preview_empty_heading'))
            ->emptyStateDescription(__('export-scheduler::scheduler.preview_empty_description'))
            ->columns($this->getReportColumns())
            ->records(fn (int $page, int $recordsPerPage, ?string $search, ?string $sortColumn, ?string $sortDirection): LengthAwarePaginator => $this->paginateRows(
                search: $search,
                sortColumn: $sortColumn,
                sortDirection: $sortDirection,
                page: $page,
                recordsPerPage: $recordsPerPage,
            ));
    }

    /**
     * @return array<TextColumn>
     */
    protected function getReportColumns(): array
    {
        return collect($this->getResultColumns())
            ->map(fn (string $label, string $name): TextColumn => TextColumn::make($name)
                ->label($label)
                // Names can be relation paths or arbitrary SQL aliases, so read the row's
                // literal key rather than letting a dot be treated as a nested path.
                ->state(fn (array $record) => $record[$name] ?? null)
                ->sortable()
                ->wrap())
            ->values()
            ->all();
    }

    /**
     * Filter, order and page the report in PHP.
     */
    protected function paginateRows(?string $search, ?string $sortColumn, ?string $sortDirection, int $page, int $recordsPerPage): LengthAwarePaginator
    {
        $rows = $this->getResultRows();

        if (filled($search)) {
            $search = Str::lower($search);

            $rows = $rows->filter(fn (array $row): bool => collect($row)
                ->contains(fn ($value): bool => str_contains(Str::lower((string) $value), $search)));
        }

        if (filled($sortColumn) && array_key_exists($sortColumn, $this->getResultColumns())) {
            $rows = $rows->sortBy(
                callback: fn (array $row) => $row[$sortColumn] ?? null,
                options: SORT_NATURAL | SORT_FLAG_CASE,
                descending: $sortDirection === 'desc',
            );
        }

        $total = $rows->count();

        return new Paginator(
            items: $rows->forPage($page, $recordsPerPage)->all(),
            total: $total,
            perPage: $recordsPerPage,
            currentPage: $page,
        );
    }

    /**
     * Every row of the report, formatted for display.
     */
    protected function getResultRows(): Collection
    {
        if ($this->resultRows !== null) {
            return $this->resultRows;
        }

        $rows = $this->getRecord()->isSqlQuery()
            ? $this->getSqlQueryRows()
            : $this->getExporterRows();

        $maxRows = $this->getMaxRows();

        if ($maxRows && $rows->count() > $maxRows) {
            $this->isTruncated = true;
            $rows = $rows->take($maxRows);
        }

        return $this->resultRows = $rows;
    }

    /**
     * @return array<string, string>
     */
    protected function getResultColumns(): array
    {
        if ($this->resultColumns !== null) {
            return $this->resultColumns;
        }

        // SQL query columns are only known once the query has run.
        $this->getResultRows();

        return $this->resultColumns ?? [];
    }

    protected function getMaxRows(): ?int
    {
        $maxRows = config('export-scheduler.viewer_max_rows');

        return $maxRows ? (int) $maxRows : null;
    }

    /**
     * Rows for an exporter report, formatted through the exporter's own columns so the
     * viewer, the download and the scheduled export all show the same values.
     */
    protected function getExporterRows(): Collection
    {
        /** @var ExportSchedule $schedule */
        $schedule = $this->getRecord();
        $exporterClass = $schedule->exporter;

        if (blank($exporterClass) || ! class_exists($exporterClass) || ! method_exists($exporterClass, 'getModel')) {
            $this->resultColumns = [];

            return collect();
        }

        $columns = collect($schedule->columns ?? [])
            ->filter(fn ($column): bool => filled($column['name'] ?? null));

        if ($columns->isEmpty()) {
            $columns = ExportSchedule::getDefaultColumnsForExporter($exporterClass);
        }

        // A saved column the exporter no longer defines cannot be formatted, so drop it.
        $definedColumns = ExportSchedule::getDefaultColumnsForExporter($exporterClass)
            ->pluck('name')
            ->all();

        $columnMap = $columns
            ->filter(fn (array $column): bool => in_array($column['name'], $definedColumns, strict: true))
            ->mapWithKeys(fn (array $column): array => [$column['name'] => $column['label'] ?? $column['name']])
            ->all();

        $this->resultColumns = $columnMap;

        if ($columnMap === []) {
            return collect();
        }

        try {
            $exporter = $this->makeExporter($exporterClass, $columnMap);
            $query = (new ScheduledExporter($schedule))->getQuery();

            foreach ($exporter->getCachedColumns() as $column) {
                $column->applyRelationshipAggregates($query);
                $column->applyEagerLoading($query);
            }

            $names = array_keys($columnMap);

            return $query->cursor()
                ->mapWithKeys(fn (Model $record): array => [
                    $record->getKey() => array_combine($names, $exporter($record)),
                ])
                ->collect();
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());

            return collect();
        }
    }

    /**
     * The exporter needs an Export to be constructed, but viewing a report must never
     * create one, so it is left unsaved.
     */
    protected function makeExporter(string $exporterClass, array $columnMap): Exporter
    {
        $export = new Export;
        $export->exporter = $exporterClass;

        return $export->getExporter(columnMap: $columnMap, options: []);
    }

    /**
     * Rows for a SQL query report. Columns are whatever the query selected.
     */
    protected function getSqlQueryRows(): Collection
    {
        $sql = $this->getRecord()->sql_query;

        if (blank($sql) || ! empty(ExportSchedule::validateSqlQuery($sql))) {
            $this->resultColumns = [];

            return collect();
        }

        try {
            $result = SqlQueryResult::run($sql);
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            $this->resultColumns = [];

            return collect();
        }

        $this->resultColumns = collect($result->columns)
            ->mapWithKeys(fn (string $column): array => [$column => $this->labelSqlQueryColumn($column)])
            ->all();

        return collect($result->rows);
    }

    /**
     * Tidy up a plain snake_case column name, but leave anything the query author aliased
     * deliberately exactly as they wrote it.
     */
    protected function labelSqlQueryColumn(string $column): string
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $column)) {
            return $column;
        }

        return (string) str($column)->replace('_', ' ')->headline();
    }
}
