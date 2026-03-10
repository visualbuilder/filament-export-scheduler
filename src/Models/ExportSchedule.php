<?php

namespace Visualbuilder\ExportScheduler\Models;

use Carbon\Carbon;
use Cron\CronExpression;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Visualbuilder\ExportScheduler\Enums\DateRange;
use Visualbuilder\ExportScheduler\Enums\DayOfWeek;
use Visualbuilder\ExportScheduler\Enums\Month;
use Visualbuilder\ExportScheduler\Enums\ReportType;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;

/**
 * App\Models\ExportSchedule
 *
 * @property int $id
 * @property string $name
 * @property string $exporter
 * @property array $columns
 * @property bool $enabled
 * @property ScheduleFrequency $schedule_frequency
 * @property string $schedule_time
 * @property string|null $cron
 * @property int|null $schedule_day_of_week
 * @property int|null $schedule_day_of_month
 * @property string|null $schedule_month
 * @property string $schedule_timezone
 * @property array|null $formats
 * @property DateRange|null $date_range
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property \Illuminate\Support\Carbon|null $next_run_at
 * @property \Illuminate\Support\Carbon|null $last_run_at
 * @property \Illuminate\Support\Carbon|null $last_successful_run_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string|null $date_range_label
 * @property-read Carbon|null $ends_at
 * @property-read string $ends_at_formatted
 * @property-read string $frequency
 * @property-read Carbon|null $starts_at
 * @property-read string $starts_at_formatted
 * @property-read Model|null $owner
 *
 * @method static Builder|ExportSchedule newModelQuery()
 * @method static Builder|ExportSchedule newQuery()
 * @method static Builder|ExportSchedule query()
 * @method static Builder|ExportSchedule whereColumns($value)
 * @method static Builder|ExportSchedule whereCreatedAt($value)
 * @method static Builder|ExportSchedule whereCron($value)
 * @method static Builder|ExportSchedule whereDateRange($value)
 * @method static Builder|ExportSchedule whereEnabled($value)
 * @method static Builder|ExportSchedule whereExporter($value)
 * @method static Builder|ExportSchedule whereFormats($value)
 * @method static Builder|ExportSchedule whereId($value)
 * @method static Builder|ExportSchedule whereNextRunAt($value)
 * @method static Builder|ExportSchedule whereLastRunAt($value)
 * @method static Builder|ExportSchedule whereLastSuccessfulRunAt($value)
 * @method static Builder|ExportSchedule whereName($value)
 * @method static Builder|ExportSchedule whereOwnerId($value)
 * @method static Builder|ExportSchedule whereOwnerType($value)
 * @method static Builder|ExportSchedule whereScheduleDayOfMonth($value)
 * @method static Builder|ExportSchedule whereScheduleDayOfWeek($value)
 * @method static Builder|ExportSchedule whereScheduleFrequency($value)
 * @method static Builder|ExportSchedule whereScheduleMonth($value)
 * @method static Builder|ExportSchedule whereScheduleTime($value)
 * @method static Builder|ExportSchedule whereScheduleTimezone($value)
 * @method static Builder|ExportSchedule whereUpdatedAt($value)
 */
class ExportSchedule extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'report_type',
        'sql_query',
        'columns',
        'exporter',
        'date_range',
        'owner_id',
        'owner_type',
        'schedule_frequency',
        'schedule_time',
        'schedule_day_of_week',
        'schedule_day_of_month',
        'schedule_month',
        'schedule_timezone',
        'formats',
        'next_run_at',
        'last_run_at',
        'last_successful_run_at',
        'enabled',
        'cron',
        'cc',
        'dynamic_owner_enabled',
        'dynamic_owner_attribute',
        'filters',
    ];

    protected $attributes = [
        'report_type' => 'exporter',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    public function casts()
    {
        return [
            'columns' => 'array',
            'available_columns' => 'array',
            'formats' => AsEnumCollection::of(ExportFormat::class),
            'cc' => 'array',
            'dynamic_owner_enabled' => 'boolean',
            'enabled' => 'boolean',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'last_successful_run_at' => 'datetime',
            'schedule_day_of_week' => DayOfWeek::class,
            'schedule_day_of_month' => 'integer',
            'schedule_month' => Month::class,
            'schedule_start_month' => Month::class,
            'date_range' => DateRange::class,
            'report_type' => ReportType::class,
            'schedule_frequency' => ScheduleFrequency::class,
            'filters' => 'array',
        ];
    }

    protected static function booted()
    {
        self::saving(function (ExportSchedule $exportSchedule) {
            // Prevent non-developers from saving SQL query reports
            if ($exportSchedule->isDirty('report_type')
                && $exportSchedule->report_type === ReportType::SQL_QUERY
            ) {
                $roles = config('export-scheduler.sql_query_roles', []);
                $user = auth()->user();

                if (! empty($roles) && $user && method_exists($user, 'hasRole') && ! $user->hasRole($roles)) {
                    throw new \Illuminate\Auth\Access\AuthorizationException(
                        'You do not have permission to create SQL query reports.'
                    );
                }
            }

            if (is_null($exportSchedule->next_run_at)) {
                $exportSchedule->next_run_at = $exportSchedule->calculateNextRun();
            }
        });

        self::updating(function (ExportSchedule $exportSchedule) {
            // Only recalculate next_run_at if schedule-related fields are changing
            // Don't recalculate if only last_run_at or next_run_at are being updated
            $scheduleFields = [
                'schedule_frequency',
                'schedule_time',
                'schedule_day_of_week',
                'schedule_day_of_month',
                'schedule_month',
                'schedule_timezone',
                'cron',
            ];

            if ($exportSchedule->isDirty($scheduleFields)) {
                $exportSchedule->next_run_at = null;
                $exportSchedule->next_run_at = $exportSchedule->calculateNextRun();
            }
        });
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeNextRunDue(Builder $query): Builder
    {
        return $query
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', Carbon::now());
    }

    public function getStartsAtAttribute(): ?Carbon
    {
        return $this->date_range?->getDateRange()['start'] ?? null;
    }

    public function getEndsAtAttribute(): ?Carbon
    {
        return $this->date_range?->getDateRange()['end'] ?? null;
    }

    public function getStartsAtFormattedAttribute(): string
    {
        return $this->starts_at ? $this->starts_at->format("l jS F Y \a\t h:i A") : '';
    }

    public function getEndsAtFormattedAttribute(): string
    {
        return $this->ends_at ? $this->ends_at->format("l jS F Y \a\t h:i A") : '';
    }

    public function getFrequencyAttribute(): string
    {
        return $this->schedule_frequency->getLabel();
    }

    public function getDateRangeLabelAttribute(): ?string
    {
        return $this->date_range?->getLabel();
    }

    public function getAvailableColumnsAttribute(): Collection
    {
        $columns = is_string($this->columns) ? json_decode($this->columns, true) : $this->columns;
        $selectedNames = array_column($columns, 'name');

        return $this->default_columns->reject(function ($column) use ($selectedNames) {
            return in_array($column['name'], $selectedNames);
        });

    }

    public function getColumnCountAttribute(): int
    {
        return $this->getDefaultColumnsAttribute()->count();
    }

    public function getDefaultColumnsAttribute(): Collection
    {
        return static::getDefaultColumnsForExporter($this->exporter);
    }

    public static function getDefaultColumnsForExporter(string $exporter): Collection
    {
        if (! class_exists($exporter) || ! method_exists($exporter, 'getColumns')) {
            return collect();
        }

        return collect($exporter::getColumns())
            ->filter(fn ($column) => $column instanceof ExportColumn) // Ensure only ExportColumn instances
            ->map(fn (ExportColumn $column) => [
                'name' => $column->getName(),
                'label' => $column->getLabel() ?? $column->getName(),
            ]);
    }

    public function calculateNextRun(): ?Carbon
    {
        return match ($this->schedule_frequency) {
            ScheduleFrequency::DAILY => $this->getNextDailyRun(),
            ScheduleFrequency::WEEKLY => $this->getNextWeeklyRun(),
            ScheduleFrequency::MONTHLY => $this->getNextMonthlyRun(),
            ScheduleFrequency::QUARTERLY => $this->getNextYearlyRun(4),
            ScheduleFrequency::HALF_YEARLY => $this->getNextYearlyRun(2),
            ScheduleFrequency::YEARLY => $this->getNextYearlyRun(),
            ScheduleFrequency::CRON => $this->getNextCronRun()
        };
    }

    protected function getNextDailyRun(): Carbon
    {
        $nextRunAt = $this->next_run_at ?? Carbon::parse($this->schedule_time);
        while ($nextRunAt->lessThanOrEqualTo(now())) {
            $nextRunAt->addDay();
        }

        return $nextRunAt;
    }

    protected function getNextWeeklyRun(): Carbon
    {
        $nextRunAt = $this->next_run_at ?? Carbon::parse($this->schedule_time)->weekday($this->schedule_day_of_week->value);
        while ($nextRunAt->lessThanOrEqualTo(now())) {
            $nextRunAt->addWeek();
        }

        return $nextRunAt;
    }

    protected function getNextMonthlyRun(): Carbon
    {
        $nextRunAt = $this->next_run_at ?? Carbon::parse($this->schedule_time)->setDay($this->schedule_day_of_month);
        while ($nextRunAt->lessThanOrEqualTo(now())) {
            $nextMonth = $nextRunAt->copy()->addMonthNoOverflow();
            $lastDayOfTheMonth = $nextMonth->copy()->endOfMonth()->day;

            if ($this->schedule_day_of_month < 0) {
                // -1 => last day of the month
                $nextRunAt = $nextMonth->copy()->endOfMonth()->setTime($nextMonth->hour, $nextMonth->minute, $nextMonth->second);
            } elseif ($lastDayOfTheMonth < $this->schedule_day_of_month) {
                // if the day is 29, 30 or 31 & isn't
                // a valid date for that month, set
                // it to the last day of that month
                $nextRunAt = $nextMonth;
            } elseif ($lastDayOfTheMonth >= $this->schedule_day_of_month) {
                $nextRunAt = $nextMonth->setDay($this->schedule_day_of_month);
            } else {
                $nextRunAt->addMonth();
            }
        }

        return $nextRunAt;
    }

    protected function getNextYearlyRun(int $numOfTimesInAYear = 1): Carbon
    {
        $numOfMonthsInAYear = 12 / $numOfTimesInAYear;
        $nextRunAt = $this->next_run_at ?? Carbon::parse($this->schedule_time)->setMonth($this->schedule_month?->value)->setDay($this->schedule_day_of_month);

        while ($nextRunAt->lessThanOrEqualTo(now())) {
            $next = $nextRunAt->copy()->addMonthsNoOverflow($numOfMonthsInAYear);
            $lastDayOfTheMonth = $next->copy()->endOfMonth()->day;

            if ($lastDayOfTheMonth < $this->schedule_day_of_month) {
                $nextRunAt = $next;
            } elseif ($lastDayOfTheMonth >= $this->schedule_day_of_month) {
                // if the day is 29, 30 or 31 & isn't
                // a valid date for that month, set
                // it to the last day of that month
                $nextRunAt = $next->setDay($this->schedule_day_of_month);
            } else {
                $nextRunAt->addMonths($numOfMonthsInAYear);
            }
        }

        return $nextRunAt;
    }

    protected function getNextCronRun(): Carbon
    {
        return Carbon::instance((new CronExpression($this->cron))->getNextRunDate($this->next_run_at ?? 'now'));
    }

    public function shouldRunNow(): bool
    {
        return match ($this->schedule_frequency) {
            ScheduleFrequency::DAILY => $this->shouldRunDailyNow(),
            ScheduleFrequency::WEEKLY => $this->shouldRunWeeklyNow(),
            ScheduleFrequency::MONTHLY => $this->shouldRunMonthlyNow(),
            ScheduleFrequency::QUARTERLY => $this->shouldRunYearlyNow(4),
            ScheduleFrequency::HALF_YEARLY => $this->shouldRunYearlyNow(2),
            ScheduleFrequency::YEARLY => $this->shouldRunYearlyNow(),
            ScheduleFrequency::CRON => $this->shouldRunCronNow()
        };
    }

    protected function shouldRunDailyNow(): bool
    {
        $scheduleTime = Carbon::today()->setTimeFromTimeString($this->schedule_time);
        return now()->greaterThanOrEqualTo($scheduleTime);
    }

    protected function shouldRunWeeklyNow(): bool
    {
        $today = now();
        $scheduleTime = Carbon::today()->setTimeFromTimeString($this->schedule_time);

        // Check if today is the scheduled day of week and we've passed the scheduled time
        return $today->dayOfWeek === $this->schedule_day_of_week->value
            && $today->greaterThanOrEqualTo($scheduleTime);
    }

    protected function shouldRunMonthlyNow(): bool
    {
        $today = now();
        $scheduleTime = Carbon::today()->setTimeFromTimeString($this->schedule_time);

        // Check if today is the scheduled day of month and we've passed the scheduled time
        return $today->day === $this->schedule_day_of_month
            && $today->greaterThanOrEqualTo($scheduleTime);
    }

    protected function shouldRunYearlyNow(int $numOfTimesInAYear = 1): bool
    {
        $today = now();
        $scheduleTime = Carbon::today()->setTimeFromTimeString($this->schedule_time);

        // Check if today is the correct day and we've passed the scheduled time
        if ($today->day !== $this->schedule_day_of_month || $today->lessThan($scheduleTime)) {
            return false;
        }

        // Calculate the interval in months
        $numOfMonthsInAYear = 12 / $numOfTimesInAYear;

        // Check if current month is in the cycle
        // Starting from schedule_month, runs every $numOfMonthsInAYear months
        $monthDiff = ($today->month - $this->schedule_month?->value) % 12;
        if ($monthDiff < 0) {
            $monthDiff += 12;
        }

        return $monthDiff % $numOfMonthsInAYear === 0;
    }

    protected function shouldRunCronNow(): bool
    {
        // For cron with null next_run_at, check if we're past the next scheduled time
        $cron = new CronExpression($this->cron);
        $previousRun = Carbon::instance($cron->getPreviousRunDate('now'));

        // If last_run_at exists and is after the previous cron time, don't run
        if ($this->last_run_at && $this->last_run_at->greaterThanOrEqualTo($previousRun)) {
            return false;
        }

        return true;
    }

    public function isSqlQuery(): bool
    {
        return $this->report_type === ReportType::SQL_QUERY;
    }

    /**
     * Validate that the SQL query is a safe SELECT statement.
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
                $errors[] = "Prohibited keyword detected: " . str_replace('\\s+', ' ', $keyword);
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

    public function getCcCountAttribute(): int
    {
        return count($this->cc ?? []);
    }

    public function willLogoutUser(): bool
    {
        return ! $this->isCurrentUserOwner() && $this->isSyncQueue();
    }

    public function isCurrentUserOwner(): bool
    {
        return auth()->user()
            && auth()->id() == $this->owner->id
            && get_class(auth()->user()) === get_class($this->owner);
    }

    public function isSyncQueue(): bool
    {
        $export = new Export;
        $export->exporter = $this->exporter;
        $exporter = $export->getExporter([], []);

        return $exporter->getJobQueue() === 'sync' || (config('queue.default') === 'sync');
    }
}
