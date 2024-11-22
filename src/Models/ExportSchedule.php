<?php

namespace VisualBuilder\ExportScheduler\Models;

use Carbon\Carbon;
use Cron\CronExpression;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use http\Exception\InvalidArgumentException;
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
 * @property \Illuminate\Support\Carbon|null $last_run_at
 * @property \Illuminate\Support\Carbon|null $last_successful_run_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $date_range_label
 * @property-read Carbon $ends_at
 * @property-read string $ends_at_formatted
 * @property-read string $frequency
 * @property-read Carbon|null $next_due_at
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

    protected $appends = ['next_due_at'];

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
        'columns'                => 'array',
        'available_columns'      => 'array',
        'formats'                => 'array',
        'cc'                     => 'array',
        'enabled'                => 'boolean',
        'last_run_at'            => 'datetime',
        'last_successful_run_at' => 'datetime',
        'schedule_day_of_week'   => DayOfWeek::class,
        'schedule_day_of_month'  => 'integer',
        'schedule_month'         => Month::class,
        'schedule_start_month'   => Month::class,
        'date_range'             => DateRange::class,
        'schedule_frequency'     => ScheduleFrequency::class,
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
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
                'name'  => $column->getName(),
                'label' => $column->getLabel() ?? $column->getName(),
            ]);
    }

    /**
     * Get the next due time for the schedule.
     */
    public function getNextDueAtAttribute(): ?Carbon
    {
        $baseTime = $this->getScheduleBaseTime();

        if (!$baseTime) {
            return null;
        }

        // Ensure $baseTime isn't modified directly
        $nextDue = $baseTime->copy();
        $now = now();

        switch ($this->schedule_frequency) {
            case ScheduleFrequency::DAILY:
                return $now->greaterThanOrEqualTo($nextDue) ? $nextDue->addDay() : $nextDue;

            case ScheduleFrequency::WEEKLY:
                return $this->getNextWeeklyRun($nextDue, $now);

            case ScheduleFrequency::MONTHLY:
            case ScheduleFrequency::QUARTERLY:
            case ScheduleFrequency::HALF_YEARLY:
                return $this->getNextMonthlyOrPeriodicRun($nextDue, $now);

            case ScheduleFrequency::YEARLY:
                return $this->getNextYearlyRun($baseTime, $now);

            case ScheduleFrequency::CRON:
                return $this->getNextCronRunAt();

            default:
                return null;
        }
    }

    /**
     * Get the base time for scheduling by combining last run and schedule time.
     */
    protected function getScheduleBaseTime(): ?Carbon
    {
        $lastRun = $this->last_run_at
            ? Carbon::parse($this->last_run_at)
            : now();

        if ($this->schedule_time) {
            // Combine the date from $lastRun with the time from schedule_time
            [$hour, $minute, $second] = explode(':', $this->schedule_time);

            return $lastRun->copy()->setTime($hour, $minute, $second);
        }

        return $lastRun;
    }

    /**
     * Get the next weekly run time.
     */
    protected function getNextWeeklyRun(Carbon $nextDue, Carbon $now): Carbon
    {
        return $now->greaterThanOrEqualTo($nextDue)
            ? $nextDue->addWeek()->next($this->schedule_day_of_week->value)
            : $nextDue->next($this->schedule_day_of_week->value);

    }

    /**
     * Get the next monthly, quarterly, or half-yearly run time.
     */
    protected function getNextMonthlyOrPeriodicRun(Carbon $nextDue, Carbon $now): Carbon
    {
        $monthsToAdd = match ($this->schedule_frequency) {
            ScheduleFrequency::MONTHLY     => 1,
            ScheduleFrequency::QUARTERLY   => 3,
            ScheduleFrequency::HALF_YEARLY => 6,
        };

        $startDay = $this->schedule_day_of_month ?? 1; // Default to 1st
        $startMonth = $this->schedule_month ?? $this->schedule_start_month ?? 1; // Default to January (1)

        $nextDue = $nextDue->setMonth($startMonth)->setDay($startDay);

        while ($nextDue->lessThanOrEqualTo($now)) {
            $nextDue->addMonths($monthsToAdd);
        }

        return $nextDue;
    }

    /**
     * Get the next yearly run time.
     */
    protected function getNextYearlyRun(Carbon $baseTime, Carbon $now): Carbon
    {
        $startMonth = $this->schedule_month ?? $this->schedule_start_month ?? 1; // Default to January
        $startDay = $this->schedule_day_of_month ?? 1; // Default to 1st

        $nextDue = Carbon::create(
                $now->year,
                $startMonth->value,
                $startDay,
                $baseTime->hour,
                $baseTime->minute,
                $baseTime->second,
                $baseTime->timezone,
        );


        return $now->greaterThanOrEqualTo($nextDue) ? $nextDue->addYear() : $nextDue; // Simplified conditional
    }

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
