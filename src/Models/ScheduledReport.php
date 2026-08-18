<?php

namespace Visualbuilder\ExportScheduler\Models;

use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Visualbuilder\ExportScheduler\Database\Factories\ScheduledReportFactory;
use Visualbuilder\ExportScheduler\Enums\DateRange;
use Visualbuilder\ExportScheduler\Enums\DayOfWeek;
use Visualbuilder\ExportScheduler\Enums\Month;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Models\Concerns\ResolvesDateRange;

/**
 * An optional delivery instruction attached to a {@see CustomReport}: when to run
 * it, and who receives the result.
 *
 * A report may carry many of these, or none. `date_range` and `formats` are
 * nullable overrides — null means inherit from the report, which is what every
 * migrated row does.
 *
 * @property int $id
 * @property int $custom_report_id
 * @property ScheduleFrequency $schedule_frequency
 * @property string $schedule_time
 * @property string|null $cron
 * @property DayOfWeek|null $schedule_day_of_week
 * @property int|null $schedule_day_of_month
 * @property Month|null $schedule_month
 * @property Month|null $schedule_start_month
 * @property string|null $schedule_timezone
 * @property DateRange|null $date_range
 * @property array|null $formats
 * @property string|null $recipient_type
 * @property int|null $recipient_id
 * @property array $cc
 * @property bool $enabled
 * @property bool $send_empty_report
 * @property Carbon|null $next_run_at
 * @property Carbon|null $last_run_at
 * @property Carbon|null $last_successful_run_at
 * @property-read CustomReport|null $report
 * @property-read Model|null $recipient
 * @property-read DateRange|null $resolved_date_range
 * @property-read array $resolved_formats
 * @property-read string|null $frequency
 * @property-read int $cc_count
 */
class ScheduledReport extends Model
{
    use HasFactory;
    use ResolvesDateRange;

    protected $table = 'scheduled_reports';

    protected $fillable = [
        'custom_report_id',
        'schedule_frequency',
        'schedule_time',
        'cron',
        'schedule_day_of_week',
        'schedule_day_of_month',
        'schedule_month',
        'schedule_start_month',
        'schedule_timezone',
        'date_range',
        'formats',
        'recipient_id',
        'recipient_type',
        'cc',
        'dynamic_owner_enabled',
        'dynamic_owner_attribute',
        'enabled',
        'send_empty_report',
        'next_run_at',
        'last_run_at',
        'last_successful_run_at',
    ];

    protected $casts = [
        'formats' => 'array',
        'cc' => 'array',
        'dynamic_owner_enabled' => 'boolean',
        'enabled' => 'boolean',
        'send_empty_report' => 'boolean',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'last_successful_run_at' => 'datetime',
        'schedule_day_of_week' => DayOfWeek::class,
        'schedule_day_of_month' => 'integer',
        'schedule_month' => Month::class,
        'schedule_start_month' => Month::class,
        'schedule_frequency' => ScheduleFrequency::class,
        'date_range' => DateRange::class,
    ];

    protected static function booted(): void
    {
        self::saving(function (ScheduledReport $schedule) {
            // cc holds bare ids whose class is recipient_type. Changing the type would
            // silently reinterpret every id against the new class — Admin #7 becoming
            // Associate #7 — so leftover ids cannot survive the change.
            //
            // Unless cc changed in the same save: then the caller has chosen a new list
            // for the new type and it is not leftover at all. Without that second
            // condition, switching the recipient type and adding cc people together
            // would save an empty list.
            if ($schedule->exists
                && $schedule->isDirty('recipient_type')
                && ! $schedule->isDirty('cc')) {
                $schedule->cc = [];
            }

            if (is_array($schedule->cc)) {
                $schedule->cc = array_values(array_filter(
                    array_map(fn ($id) => filled($id) ? (string) $id : null, $schedule->cc)
                ));
            }

            if (is_null($schedule->next_run_at)) {
                $schedule->next_run_at = $schedule->calculateNextRun();
            }
        });

        self::updating(function (ScheduledReport $schedule) {
            // Only recalculate next_run_at if schedule-related fields are changing.
            // Don't recalculate if only last_run_at or next_run_at are being updated.
            $scheduleFields = [
                'schedule_frequency',
                'schedule_time',
                'schedule_day_of_week',
                'schedule_day_of_month',
                'schedule_month',
                'schedule_timezone',
                'cron',
            ];

            if ($schedule->isDirty($scheduleFields)) {
                $schedule->next_run_at = null;
                $schedule->next_run_at = $schedule->calculateNextRun();
            }
        });
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(CustomReport::class, 'custom_report_id');
    }

    public function recipient(): MorphTo
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

    /*
    |--------------------------------------------------------------------------
    | Date range and formats
    |--------------------------------------------------------------------------
    |
    | Both belong to the schedule alone. They were once report-level defaults a
    | schedule could override, but the report no longer carries either, so there
    | is nothing left to inherit and these no longer fall back to it.
    |
    | Kept as accessors rather than folded into their callers: they are the
    | documented read path, and the format column is still an array for the sake
    | of the export pipeline even though the UI now writes a single value.
    */

    public function getResolvedDateRangeAttribute(): ?DateRange
    {
        return $this->date_range;
    }

    public function getResolvedFormatsAttribute(): array
    {
        return $this->formats ?? [];
    }

    public function effectiveDateRange(): ?DateRange
    {
        return $this->resolved_date_range;
    }

    /*
    |--------------------------------------------------------------------------
    | Next run calculation
    |--------------------------------------------------------------------------
    */

    public function calculateNextRun(): ?Carbon
    {
        return match ($this->schedule_frequency) {
            ScheduleFrequency::DAILY => $this->getNextDailyRun(),
            ScheduleFrequency::WEEKLY => $this->getNextWeeklyRun(),
            ScheduleFrequency::MONTHLY => $this->getNextMonthlyRun(),
            ScheduleFrequency::QUARTERLY => $this->getNextYearlyRun(4),
            ScheduleFrequency::HALF_YEARLY => $this->getNextYearlyRun(2),
            ScheduleFrequency::YEARLY => $this->getNextYearlyRun(),
            ScheduleFrequency::CRON => $this->getNextCronRun(),
            default => null,
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

    /*
    |--------------------------------------------------------------------------
    | Due-now checks
    |--------------------------------------------------------------------------
    */

    public function shouldRunNow(): bool
    {
        return match ($this->schedule_frequency) {
            ScheduleFrequency::DAILY => $this->shouldRunDailyNow(),
            ScheduleFrequency::WEEKLY => $this->shouldRunWeeklyNow(),
            ScheduleFrequency::MONTHLY => $this->shouldRunMonthlyNow(),
            ScheduleFrequency::QUARTERLY => $this->shouldRunYearlyNow(4),
            ScheduleFrequency::HALF_YEARLY => $this->shouldRunYearlyNow(2),
            ScheduleFrequency::YEARLY => $this->shouldRunYearlyNow(),
            ScheduleFrequency::CRON => $this->shouldRunCronNow(),
            default => false,
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

        return $today->dayOfWeek === $this->schedule_day_of_week->value
            && $today->greaterThanOrEqualTo($scheduleTime);
    }

    protected function shouldRunMonthlyNow(): bool
    {
        $today = now();
        $scheduleTime = Carbon::today()->setTimeFromTimeString($this->schedule_time);

        return $today->day === $this->schedule_day_of_month
            && $today->greaterThanOrEqualTo($scheduleTime);
    }

    protected function shouldRunYearlyNow(int $numOfTimesInAYear = 1): bool
    {
        $today = now();
        $scheduleTime = Carbon::today()->setTimeFromTimeString($this->schedule_time);

        if ($today->day !== $this->schedule_day_of_month || $today->lessThan($scheduleTime)) {
            return false;
        }

        $numOfMonthsInAYear = 12 / $numOfTimesInAYear;

        $monthDiff = ($today->month - $this->schedule_month?->value) % 12;
        if ($monthDiff < 0) {
            $monthDiff += 12;
        }

        return $monthDiff % $numOfMonthsInAYear === 0;
    }

    protected function shouldRunCronNow(): bool
    {
        $cron = new CronExpression($this->cron);
        $previousRun = Carbon::instance($cron->getPreviousRunDate('now'));

        if ($this->last_run_at && $this->last_run_at->greaterThanOrEqualTo($previousRun)) {
            return false;
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Display and run-time helpers
    |--------------------------------------------------------------------------
    */

    public function getFrequencyAttribute(): ?string
    {
        return $this->schedule_frequency?->getLabel();
    }

    public function getCcCountAttribute(): int
    {
        return count($this->cc ?? []);
    }

    public function willLogoutUser(): bool
    {
        return ! $this->isCurrentUserRecipient() && $this->isSyncQueue();
    }

    /**
     * Is the signed-in user the one this schedule delivers to? A sync-queue export
     * re-authenticates as the recipient, so running it as anyone else logs you out.
     */
    public function isCurrentUserRecipient(): bool
    {
        $user = auth()->user();

        return $user
            && $this->recipient
            && (string) $user->getKey() === (string) $this->recipient->getKey()
            && $user::class === $this->recipient::class;
    }

    public function isSyncQueue(): bool
    {
        return $this->report?->isSyncQueue() ?? (config('queue.default') === 'sync');
    }

    protected static function newFactory(): ScheduledReportFactory
    {
        return ScheduledReportFactory::new();
    }
}
