<?php

namespace VisualBuilder\ExportScheduler\Models;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use VisualBuilder\ExportScheduler\Enums\DateRange;
use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;

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
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'columns' => 'array',
        'formats' => 'array',
        'last_run_at' => 'datetime',
        'last_successful_run_at' => 'datetime',
        'date_range' => DateRange::class,
        'schedule_frequency' => ScheduleFrequency::class,
    ];


    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the next due time for the schedule.
     */
    public function getNextDueAtAttribute(): ?Carbon
    {
        $baseTime = $this->getScheduleBaseTime();

        if (! $baseTime) {
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
            ScheduleFrequency::MONTHLY => 1,
            ScheduleFrequency::QUARTERLY => 3,
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


    protected function getNextCronRunAt(): ?Carbon
    {
        if (! $this->custom_cron_expression) {
            return null;
        }

        $cron = new CronExpression($this->custom_cron_expression);

        return Carbon::instance($cron->getNextRunDate($this->last_run_at ?? 'now'));
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
}
