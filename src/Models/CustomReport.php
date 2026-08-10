<?php

namespace Visualbuilder\ExportScheduler\Models;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Visualbuilder\ExportScheduler\Database\Factories\CustomReportFactory;
use Visualbuilder\ExportScheduler\Enums\DateRange;
use Visualbuilder\ExportScheduler\Enums\ReportType;
use Visualbuilder\ExportScheduler\Enums\ReportVisibility;
use Visualbuilder\ExportScheduler\Models\Concerns\ResolvesDateRange;

/**
 * The definition of a report: what to export, which columns, which filters, and
 * who may look at it. Owned by whoever built it.
 *
 * Delivery is a separate, optional concern — see {@see ScheduledReport}. A report
 * may have no schedules at all and still be viewed and downloaded in the panel.
 *
 * @property int $id
 * @property string $name
 * @property ReportType $report_type
 * @property string|null $sql_query
 * @property string|null $exporter
 * @property array|null $columns
 * @property array|null $filters
 * @property DateRange|null $date_range
 * @property array|null $formats
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property ReportVisibility $visibility
 * @property string|null $visible_to_type
 * @property array|null $visible_to_ids
 * @property-read Model|null $owner
 * @property-read Collection<ScheduledReport> $schedules
 * @property-read Collection $default_columns
 * @property-read Collection $available_columns
 * @property-read int $column_count
 */
class CustomReport extends Model
{
    use HasFactory;
    use ResolvesDateRange;

    protected $table = 'custom_reports';

    protected $fillable = [
        'name',
        'report_type',
        'sql_query',
        'exporter',
        'columns',
        'filters',
        'date_range',
        'formats',
        'owner_id',
        'owner_type',
        'visibility',
        'visible_to_type',
        'visible_to_ids',
    ];

    protected $casts = [
        'columns' => 'array',
        'available_columns' => 'array',
        'filters' => 'array',
        'formats' => 'array',
        'date_range' => DateRange::class,
        'report_type' => ReportType::class,
        'visibility' => ReportVisibility::class,
        'visible_to_ids' => 'array',
    ];

    protected $attributes = [
        'report_type' => 'exporter',
        'visibility' => 'owner',
    ];

    protected static function booted(): void
    {
        self::saving(function (CustomReport $report) {
            // Prevent non-developers from saving SQL query reports
            if ($report->isDirty('report_type') && $report->report_type === ReportType::SQL_QUERY) {
                $roles = config('export-scheduler.sql_query_roles', []);
                $user = auth()->user();

                if (! empty($roles) && $user && method_exists($user, 'hasRole') && ! $user->hasRole($roles)) {
                    throw new \Illuminate\Auth\Access\AuthorizationException(
                        'You do not have permission to create SQL query reports.'
                    );
                }
            }

            // visible_to_ids holds bare ids whose class is visible_to_type. Changing the
            // type would silently reinterpret every id against the new class, so leftover
            // ids cannot survive the change.
            //
            // Unless the ids changed in the same save: then the caller has chosen a new
            // list for the new type and it is not leftover at all. Without that second
            // condition, picking a type and its people together would save an empty list.
            if ($report->exists
                && $report->isDirty('visible_to_type')
                && ! $report->isDirty('visible_to_ids')) {
                $report->visible_to_ids = [];
            }

            // Neither column means anything unless the mode uses it.
            if ($report->visibility === ReportVisibility::OWNER) {
                $report->visible_to_type = null;
                $report->visible_to_ids = [];
            } elseif ($report->visibility === ReportVisibility::USER_TYPE) {
                $report->visible_to_ids = [];
            }

            // Ids are stored as strings so integer and UUID keys compare identically,
            // and whereJsonContains matches on every driver.
            if (is_array($report->visible_to_ids)) {
                $report->visible_to_ids = array_values(array_map(
                    fn ($id) => (string) $id,
                    $report->visible_to_ids
                ));
            }
        });
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ScheduledReport::class, 'custom_report_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Ownership and visibility
    |--------------------------------------------------------------------------
    |
    | Deliberately plain methods rather than a Policy, so a consuming app is not
    | forced to register one. Visibility grants read access only: editing,
    | deleting, running and schedule management stay with the owner whatever the
    | mode.
    */

    public function isOwnedBy(?Model $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->owner_type === $user::class
            && (string) $this->owner_id === (string) $user->getKey();
    }

    public function isVisibleTo(?Model $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->isOwnedBy($user)) {
            return true;
        }

        // The class check applies to named users too: an id collision across two
        // user models must not grant access.
        if ($this->visible_to_type !== $user::class) {
            return false;
        }

        return match ($this->visibility) {
            ReportVisibility::USER_TYPE => true,
            ReportVisibility::NAMED_USERS => in_array(
                (string) $user->getKey(),
                $this->visible_to_ids ?? [],
                strict: true
            ),
            default => false,
        };
    }

    public function scopeVisibleTo(Builder $query, ?Model $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q
                ->where('owner_type', $user::class)
                ->where('owner_id', $user->getKey()))
            ->orWhere(fn (Builder $q) => $q
                ->where('visibility', ReportVisibility::USER_TYPE)
                ->where('visible_to_type', $user::class))
            ->orWhere(fn (Builder $q) => $q
                ->where('visibility', ReportVisibility::NAMED_USERS)
                ->where('visible_to_type', $user::class)
                ->whereJsonContains('visible_to_ids', (string) $user->getKey())));
    }

    /*
    |--------------------------------------------------------------------------
    | Report definition
    |--------------------------------------------------------------------------
    */

    public function effectiveDateRange(): ?DateRange
    {
        return $this->date_range;
    }

    public function isSqlQuery(): bool
    {
        return $this->report_type === ReportType::SQL_QUERY;
    }

    /**
     * Validate that the SQL query is a safe SELECT statement.
     *
     * @return array<string> validation errors, empty when the query is acceptable
     */
    public static function validateSqlQuery(string $sql): array
    {
        $errors = [];
        $normalised = preg_replace('/\s+/', ' ', trim($sql));

        if (! preg_match('/^\s*SELECT\b/i', $normalised)) {
            $errors[] = 'Query must begin with SELECT.';
        }

        $dangerous = [
            'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE',
            'REPLACE', 'RENAME', 'GRANT', 'REVOKE', 'EXEC', 'EXECUTE',
            'INTO\s+OUTFILE', 'INTO\s+DUMPFILE', 'LOAD_FILE',
        ];

        foreach ($dangerous as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/i', $normalised)) {
                $errors[] = 'Prohibited keyword detected: ' . str_replace('\\s+', ' ', $keyword);
            }
        }

        // Block any semicolons — they break subquery wrapping and could enable injection
        $withoutStrings = preg_replace("/'[^']*'/", '', $normalised);
        $withoutStrings = preg_replace('/"[^"]*"/', '', $withoutStrings);
        if (str_contains($withoutStrings, ';')) {
            $errors[] = 'Semicolons are not allowed in the query.';
        }

        return $errors;
    }

    public static function getDefaultColumnsForExporter(string $exporter): Collection
    {
        if (! class_exists($exporter) || ! method_exists($exporter, 'getColumns')) {
            return collect();
        }

        return collect($exporter::getColumns())
            ->filter(fn ($column) => $column instanceof ExportColumn)
            ->map(fn (ExportColumn $column) => [
                'name' => $column->getName(),
                'label' => $column->getLabel() ?? $column->getName(),
            ]);
    }

    public function getDefaultColumnsAttribute(): Collection
    {
        return static::getDefaultColumnsForExporter((string) $this->exporter);
    }

    public function getAvailableColumnsAttribute(): Collection
    {
        $columns = is_string($this->columns) ? json_decode($this->columns, true) : $this->columns;
        $selectedNames = array_column($columns ?? [], 'name');

        return $this->default_columns->reject(
            fn ($column) => in_array($column['name'], $selectedNames)
        );
    }

    public function getColumnCountAttribute(): int
    {
        return $this->getDefaultColumnsAttribute()->count();
    }

    /**
     * Would running this report block the request? Reads the exporter's own queue,
     * so it lives with the definition rather than with a schedule.
     */
    public function isSyncQueue(): bool
    {
        if (config('queue.default') === 'sync') {
            return true;
        }

        // SQL query reports have no exporter class to ask for a queue.
        if (! class_exists((string) $this->exporter)) {
            return false;
        }

        $export = new Export;
        $export->exporter = $this->exporter;

        return $export->getExporter([], [])->getJobQueue() === 'sync';
    }

    protected static function newFactory(): CustomReportFactory
    {
        return CustomReportFactory::new();
    }
}
