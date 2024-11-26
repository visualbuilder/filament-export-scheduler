<?php

namespace VisualBuilder\ExportScheduler\Models;

use Carbon\Carbon;
use Cron\CronExpression;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use VisualBuilder\ExportScheduler\Enums\DateRange;
use VisualBuilder\ExportScheduler\Enums\DayOfWeek;
use VisualBuilder\ExportScheduler\Enums\Month;
use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;

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
 * @property-read string $date_range_label
 * @property-read Carbon $ends_at
 * @property-read string $ends_at_formatted
 * @property-read string $frequency
 * @property-read Carbon|null $starts_at
 * @property-read string $starts_at_formatted
 * @property-read Model|null $owner
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
        'cc'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'columns' => 'array',
        'available_columns' => 'array',
        'formats' => 'array',
        'cc' => 'array',
        'enabled' => 'boolean',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'last_successful_run_at' => 'datetime',
        'schedule_day_of_week' => DayOfWeek::class,
        'schedule_day_of_month' => 'integer',
        'schedule_month' => Month::class,
        'schedule_start_month' => Month::class,
        'date_range' => DateRange::class,
        'schedule_frequency' => ScheduleFrequency::class,
    ];

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

    public function getStartsAtAttribute(): Carbon
    {
        return $this->date_range->getDateRange()['start'];
    }

    public function getEndsAtAttribute(): Carbon
    {
        return $this->date_range->getDateRange()['end'];
    }

    public function getStartsAtFormattedAttribute(): string
    {
        return $this->starts_at->format("l jS F Y \a\t h:i A");
    }

    public function getEndsAtFormattedAttribute(): string
    {
        return $this->ends_at->format("l jS F Y \a\t h:i A");
    }

    public function getFrequencyAttribute(): string
    {
        return $this->schedule_frequency->getLabel();
    }

    public function getDateRangeLabelAttribute(): string
    {
        return $this->date_range->getLabel();
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
        if (!class_exists($exporter) || !method_exists($exporter, 'getColumns')) {
            return collect();
        }

        return collect($exporter::getColumns())
            ->filter(fn($column) => $column instanceof ExportColumn) // Ensure only ExportColumn instances
            ->map(fn(ExportColumn $column) => [
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
//            ScheduleFrequency::QUARTERLY => $this->getNextYearlyRun(4),
//            ScheduleFrequency::HALF_YEARLY => $this->getNextYearlyRun(2),
//            ScheduleFrequency::YEARLY => $this->getNextYearlyRun(),
//            ScheduleFrequency::CRON => $this->getNextCronRun()
        };
    }

    protected function getNextDailyRun(): Carbon
    {
        $nextRunAt = $this->next_run_at ?? Carbon::parse($this->schedule_time);
        if ($nextRunAt->lessThanOrEqualTo(now())) {
            $nextRunAt->addDay();
        }
        return $nextRunAt;
    }

    protected function getNextWeeklyRun(): Carbon
    {
        $nextRunAt = $this->next_run_at ?? Carbon::parse($this->schedule_time)->weekday($this->schedule_day_of_week->value);
        if ($nextRunAt->lessThanOrEqualTo(now())) {
            $nextRunAt->addWeek();
        }
        return $nextRunAt;
    }

    protected function getNextMonthlyRun(): Carbon
    {
        $nextRunAt = $this->next_run_at ?? Carbon::parse($this->schedule_time)->dayOfMonth($this->schedule_day_of_month);
        if ($nextRunAt->lessThanOrEqualTo(now())) {
            $nextMonth = $nextRunAt->copy()->addMonthNoOverflow();

            // if day is 29, 30, 31 and next month doesn't have date
            if ($nextMonth->copy()->endOfMonth()->day < $this->schedule_day_of_month) {
                $nextRunAt = $nextMonth;
            } else if ($nextMonth->copy()->endOfMonth()->day >= $this->schedule_day_of_month) {
                $nextRunAt = $nextMonth->setDay($this->schedule_day_of_month);
            } else {
                $nextRunAt->addMonth();
            }
        }

        return $nextRunAt;
    }

    /**
     * Get the next yearly, half-yearly  or quarterly run time.
     */
//    protected function getNextMonthlyOrPeriodicRun(Carbon $nextDue, Carbon $now): Carbon
//    {
//        $monthsToAdd = match ($this->schedule_frequency) {
//            ScheduleFrequency::MONTHLY => 1,
//            ScheduleFrequency::QUARTERLY => 3,
//            ScheduleFrequency::HALF_YEARLY => 6,
//        };
//
//        $startDay = $this->schedule_day_of_month ?? 1; // Default to 1st
//        $startMonth = $this->schedule_month ?? $this->schedule_start_month ?? 1; // Default to January (1)
//
//        $nextDue = $nextDue->setMonth($startMonth)->setDay($startDay);
//
//        while ($nextDue->lessThanOrEqualTo($now)) {
//            $nextDue->addMonths($monthsToAdd);
//        }
//
//        return $nextDue;
//    }

    /**
     * Get the next yearly run time.
     */
//    protected function getNextYearlyRun(Carbon $baseTime, Carbon $now): Carbon
//    {
//        $startMonth = $this->schedule_month ?? $this->schedule_start_month ?? 1; // Default to January
//        $startDay = $this->schedule_day_of_month ?? 1; // Default to 1st
//
//        $nextDue = Carbon::create(
//            $now->year,
//            $startMonth->value,
//            $startDay,
//            $baseTime->hour,
//            $baseTime->minute,
//            $baseTime->second,
//            $baseTime->timezone,
//        );
//
//
//        return $now->greaterThanOrEqualTo($nextDue) ? $nextDue->addYear() : $nextDue; // Simplified conditional
//    }

    protected function getNextCronRunAt(): ?Carbon
    {
        if (!$this->cron) {
            return null;
        }

        $cron = new CronExpression($this->cron);

        return Carbon::instance($cron->getNextRunDate($this->last_run_at ?? 'now'));
    }

    public function willLogoutUser(): bool
    {
        return !$this->isCurrentUserOwner() && $this->isSyncQueue();
    }

    public function isCurrentUserOwner(): bool
    {
        return auth()->user()
            && auth()->id() == $this->owner->id
            && get_class(auth()->user()) === get_class($this->owner);
    }

    public function isSyncQueue(): bool
    {
        $export = new Export();
        $export->exporter = $this->exporter;
        $exporter = $export->getExporter([], []);
        return $exporter->getJobQueue() === 'sync' || (config('queue.default') === 'sync');
    }
}
