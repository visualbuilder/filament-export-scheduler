<?php

namespace VisualBuilder\ExportScheduler\Models;

use VisualBuilder\ExportScheduler\Enums\DateRange;
use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
            'formats'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
            'columns'            => 'array',
            'formats'            => 'array',
            'date_range'         => DateRange::class,
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
     * *********************************
     * Helpers
     * *********************************
     */


    /**
     * Check if the export schedule is due to run.
     *
     * @return bool
     */
    public function isDue(): bool
    {
        $lastRun = $this->last_run_at ?? Carbon::minValue();

        switch ($this->schedule_frequency) {
            case ScheduleFrequency::DAILY->value:
                return now()->diffInDays($lastRun) >= 1;

            case ScheduleFrequency::WEEKLY->value:
                return now()->diffInWeeks($lastRun) >= 1
                    && now()->dayOfWeek === $this->schedule_day_of_week;

            case ScheduleFrequency::MONTHLY->value:
                return now()->diffInMonths($lastRun) >= 1
                    && now()->day === $this->schedule_day_of_month;

            case ScheduleFrequency::QUARTERLY->value:
                return now()->diffInMonths($lastRun) >= 3
                    && now()->day === $this->schedule_day_of_month;

            case ScheduleFrequency::HALF_YEARLY->value:
                return now()->diffInMonths($lastRun) >= 6
                    && now()->day === $this->schedule_day_of_month;

            case ScheduleFrequency::YEARLY->value:
                return now()->diffInYears($lastRun) >= 1
                    && now()->day === $this->schedule_day_of_month
                    && now()->month === $this->schedule_month;

            case ScheduleFrequency::CRON->value:
                return $this->isCronDue();

            default:
                return false;
        }
    }

    /**
     * Check if the export is due based on the cron expression.
     *
     * @return bool
     */
    protected function isCronDue(): bool
    {
        if (!$this->custom_cron_expression) {
            return false;
        }

        $cron = new CronExpression($this->custom_cron_expression);
        $nextRunAt = Carbon::instance($cron->getNextRunDate($this->last_run_at ?? 'now'));

        return now()->greaterThanOrEqualTo($nextRunAt);
    }



    /**
     * ********************************
     * Attributes
     * ********************************
     */


    public function getStartsAtAttribute():Carbon
    {
        return $this->date_range->getDateRange()['start'];
    }
    public function getEndsAtAttribute():Carbon
    {
        return $this->date_range->getDateRange()['end'];
    }

    public function getStartsAtFormattedAttribute():string
    {
        return  $this->starts_at->format(config('company.readableDateTimeDisplayFormat'));
    }
    public function getEndsAtFormattedAttribute():string
    {
        return  $this->ends_at->format(config('company.readableDateTimeDisplayFormat'));
    }

    public function getFrequencyAttribute():string
    {
        return $this->schedule_frequency->getLabel();
    }

    public function getDateRangeLabelAttribute():string
    {
        return $this->date_range->getLabel();
    }
}
