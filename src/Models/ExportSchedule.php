<?php

namespace VisualBuilder\ExportScheduler\Models;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Log;
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
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'columns' => 'array',
        'formats' => 'array',
        'date_range' => DateRange::class,
        'schedule_frequency' => ScheduleFrequency::class,
    ];

    /**
     * *********************************
     * Relations
     * *********************************
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the next due time for the schedule.
     *
     * @return Carbon|null
     */
    public function getNextDueAtAttribute(): ?Carbon
    {
        $baseTime = $this->getScheduleBaseTime();

        if (!$baseTime) {
            return null;
        }

        switch ($this->schedule_frequency) {
            case ScheduleFrequency::DAILY:
                return now()->isSameDay($baseTime) ? $baseTime : $baseTime->addDay();

            case ScheduleFrequency::WEEKLY:
                return now()->isSameDay($baseTime) ? $baseTime : $baseTime->addWeek()->next($this->schedule_day_of_week);

            case ScheduleFrequency::MONTHLY:
                return now()->isSameDay($baseTime) ? $baseTime : $baseTime->addMonth()->day($this->schedule_day_of_month);

            case ScheduleFrequency::QUARTERLY:
                return now()->isSameDay($baseTime) ? $baseTime : $baseTime->addMonths(3)->day($this->schedule_day_of_month);

            case ScheduleFrequency::HALF_YEARLY:
                return now()->isSameDay($baseTime) ? $baseTime : $baseTime->addMonths(6)->day($this->schedule_day_of_month);

            case ScheduleFrequency::YEARLY:
                return now()->isSameDay($baseTime) ? $baseTime : $baseTime->addYear()
                    ->month($this->schedule_month)
                    ->day($this->schedule_day_of_month);

            case ScheduleFrequency::CRON:
                return $this->getNextCronRunAt();

            default:
                return null;
        }
    }

    /**
     * Get the base time for scheduling by combining last run and schedule time.
     *
     * @return Carbon|null
     */
    protected function getScheduleBaseTime(): ?Carbon
    {
        // Use the last run time if available, otherwise start from now
        $lastRun = $this->last_run_at ? Carbon::parse($this->last_run_at) : now();

        if ($this->schedule_time) {
            // Combine the date from $lastRun with the time from schedule_time
            [$hour, $minute, $second] = explode(':', $this->schedule_time);
            $lastRun->setTime($hour, $minute, $second);
        }

        return $lastRun;
    }

    protected function getNextCronRunAt(): ?Carbon
    {
        if (!$this->custom_cron_expression) {
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
        return $this->starts_at->format(config('company.readableDateTimeDisplayFormat'));
    }

    public function getEndsAtFormattedAttribute(): string
    {
        return $this->ends_at->format(config('company.readableDateTimeDisplayFormat'));
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
