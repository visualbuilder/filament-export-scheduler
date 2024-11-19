<?php

namespace VisualBuilder\ExportScheduler\Models;

use Carbon\Carbon;
use Cron\CronExpression;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use VisualBuilder\ExportScheduler\Enums\DateRange;
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
 * @property string|null $schedule_day_of_week
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
 * @property-read Model|null $primaryContact
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
        'cron'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'columns'                => 'array',
        'formats'                => 'array',
        'last_run_at'            => 'datetime',
        'enabled'                => 'boolean',
        'last_successful_run_at' => 'datetime',
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
            ? $nextDue->addWeek()->next($this->schedule_day_of_week)
            : $nextDue->next($this->schedule_day_of_week);
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

        return $now->greaterThanOrEqualTo($nextDue)
            ? $nextDue->addMonths($monthsToAdd)->day($this->schedule_day_of_month)
            : $nextDue->day($this->schedule_day_of_month);
    }

    /**
     * Get the next yearly run time.
     */
    protected function getNextYearlyRun(Carbon $baseTime, Carbon $now): Carbon
    {
        $month = is_numeric($this->schedule_month)
            ? (int) $this->schedule_month
            : Carbon::parse($this->schedule_month)->month;

        $nextDue = Carbon::create(
            $now->year,
            $month,
            $this->schedule_day_of_month,
            $baseTime->hour,
            $baseTime->minute,
            $baseTime->second,
            $baseTime->timezone
        );

        if ($now->greaterThanOrEqualTo($nextDue)) {
            $nextDue->addYear();
        }

        return $nextDue;
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
        return !$this->isForThisUser() && $this->isSyncQueue();
    }

    public function isForThisUser(): bool
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
