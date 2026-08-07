<?php

namespace Visualbuilder\ExportScheduler\Filament\Resources\ExportScheduleResource\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;
use Visualbuilder\ExportScheduler\Filament\Actions\RunExport;
use Visualbuilder\ExportScheduler\Filament\Resources\ExportScheduleResource;
use Visualbuilder\ExportScheduler\Models\ExportSchedule;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;

/**
 * Previews the rows a schedule would export, without running the export.
 */
class ViewExportSchedule extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = ExportScheduleResource::class;

    /**
     * Cached first row of an SQL query report, used to derive the columns.
     */
    protected object | false | null $sqlQuerySampleRow = false;

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
        $table = $table
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('export-scheduler::scheduler.preview_empty_heading'))
            ->emptyStateDescription(__('export-scheduler::scheduler.preview_empty_description'));

        /** @var ExportSchedule $schedule */
        $schedule = $this->getRecord();

        if ($schedule->isSqlQuery()) {
            return $table
                ->modelLabel(__('export-scheduler::scheduler.preview_row'))
                ->columns($this->getSqlQueryColumns())
                ->records(fn (int $page, int $recordsPerPage): LengthAwarePaginator => $this->getSqlQueryRecords($page, $recordsPerPage));
        }

        if (! $this->hasUsableExporter()) {
            return $table
                ->modelLabel(__('export-scheduler::scheduler.preview_row'))
                ->columns([])
                ->records(fn (int $page, int $recordsPerPage): LengthAwarePaginator => $this->makeEmptyPaginator($page, $recordsPerPage));
        }

        return $table
            ->columns($this->getExporterColumns())
            ->query(fn (): Builder => (new ScheduledExporter($schedule))->getQuery());
    }

    protected function hasUsableExporter(): bool
    {
        $exporter = $this->getRecord()->exporter;

        return filled($exporter) && class_exists($exporter) && method_exists($exporter, 'getModel');
    }

    /**
     * The columns selected on the schedule, falling back to everything the exporter offers.
     *
     * @return array<TextColumn>
     */
    protected function getExporterColumns(): array
    {
        $columns = collect($this->getRecord()->columns ?? []);

        if ($columns->isEmpty()) {
            $columns = ExportSchedule::getDefaultColumnsForExporter($this->getRecord()->exporter);
        }

        return $columns
            ->filter(fn ($column): bool => filled($column['name'] ?? null))
            ->map(fn (array $column): TextColumn => TextColumn::make($column['name'])
                ->label($column['label'] ?? $column['name'])
                ->sortable()
                ->wrap())
            ->values()
            ->all();
    }

    /**
     * @return array<TextColumn>
     */
    protected function getSqlQueryColumns(): array
    {
        $row = $this->getSqlQuerySampleRow();

        if (! $row) {
            return [];
        }

        return collect(array_keys((array) $row))
            ->map(fn (string $column): TextColumn => TextColumn::make($column)
                ->label(str($column)->replace('_', ' ')->headline())
                ->sortable()
                ->wrap())
            ->all();
    }

    protected function getSqlQuerySampleRow(): ?object
    {
        if ($this->sqlQuerySampleRow !== false) {
            return $this->sqlQuerySampleRow;
        }

        return $this->sqlQuerySampleRow = $this->hasRunnableSqlQuery()
            ? $this->getSqlQuerySubquery()->limit(1)->first()
            : null;
    }

    protected function getSqlQueryRecords(int $page, int $recordsPerPage): LengthAwarePaginator
    {
        if (! $this->hasRunnableSqlQuery()) {
            return $this->makeEmptyPaginator($page, $recordsPerPage);
        }

        $query = $this->getSqlQuerySubquery();

        // Apply sorting from the table's sort column/direction, but only if the column exists in the results
        if ($sortColumn = $this->getTableSortColumn()) {
            $resultColumns = $this->getSqlQueryResultColumns();
            if (in_array($sortColumn, $resultColumns)) {
                $direction = $this->getTableSortDirection() === 'asc' ? 'asc' : 'desc';
                $query->orderBy($sortColumn, $direction);
            }
        }

        $paginator = $query->paginate(perPage: $recordsPerPage, page: $page);

        // Table columns read plain arrays when a table has no Eloquent query behind it.
        return $paginator->setCollection(
            $paginator->getCollection()->map(fn (object $row): array => (array) $row)
        );
    }

    /**
     * Get the list of column names that exist in the SQL query result set.
     *
     * @return array<string>
     */
    protected function getSqlQueryResultColumns(): array
    {
        $row = $this->getSqlQuerySampleRow();

        return $row ? array_keys((array) $row) : [];
    }

    /**
     * Wrap the report query so it can be paginated without loading every row.
     */
    protected function getSqlQuerySubquery(): QueryBuilder
    {
        return DB::table(DB::raw('(' . $this->getRecord()->sql_query . ') as export_schedule_report'));
    }

    protected function hasRunnableSqlQuery(): bool
    {
        $sql = $this->getRecord()->sql_query;

        return filled($sql) && empty(ExportSchedule::validateSqlQuery($sql));
    }

    protected function makeEmptyPaginator(int $page, int $recordsPerPage): LengthAwarePaginator
    {
        return new Paginator([], 0, $recordsPerPage, $page);
    }
}
