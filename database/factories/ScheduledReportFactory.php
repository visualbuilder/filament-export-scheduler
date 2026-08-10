<?php

namespace Visualbuilder\ExportScheduler\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Visualbuilder\ExportScheduler\Enums\DayOfWeek;
use Visualbuilder\ExportScheduler\Enums\Month;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;
use Visualbuilder\ExportScheduler\Tests\Models\User;

/**
 * Every frequency needs its own companion fields, so the default is a plain daily
 * schedule and each other frequency gets a state that fills what it requires.
 *
 * Do not randomise `schedule_frequency` in the default: a MONTHLY schedule with a
 * null `schedule_day_of_month` sends `getNextMonthlyRun()` into an endless loop,
 * and a CRON one with a null expression throws. A factory should only ever build a
 * model the application considers valid.
 */
class ScheduledReportFactory extends Factory
{
    protected $model = ScheduledReport::class;

    public function definition(): array
    {
        return [
            'custom_report_id' => CustomReport::factory(),
            'schedule_frequency' => ScheduleFrequency::DAILY,
            'schedule_time' => '09:00:00',
            'schedule_timezone' => 'UTC',
            'schedule_day_of_week' => null,
            'schedule_day_of_month' => null,
            'schedule_month' => null,
            'schedule_start_month' => null,
            'cron' => null,
            'date_range' => null,
            'formats' => null,
            'recipient_id' => User::factory(),
            'recipient_type' => User::class,
            'cc' => [],
            'dynamic_owner_enabled' => false,
            'dynamic_owner_attribute' => null,
            'enabled' => true,
            'send_empty_report' => true,
            'last_run_at' => null,
            'last_successful_run_at' => null,
        ];
    }

    public function daily(string $time = '09:00:00'): static
    {
        return $this->state([
            'schedule_frequency' => ScheduleFrequency::DAILY,
            'schedule_time' => $time,
        ]);
    }

    public function weekly(DayOfWeek $day = DayOfWeek::MONDAY): static
    {
        return $this->state([
            'schedule_frequency' => ScheduleFrequency::WEEKLY,
            'schedule_day_of_week' => $day,
        ]);
    }

    public function monthly(int $dayOfMonth = 1): static
    {
        return $this->state([
            'schedule_frequency' => ScheduleFrequency::MONTHLY,
            'schedule_day_of_month' => $dayOfMonth,
        ]);
    }

    public function quarterly(int $dayOfMonth = 1, Month $startMonth = Month::JANUARY): static
    {
        return $this->state([
            'schedule_frequency' => ScheduleFrequency::QUARTERLY,
            'schedule_day_of_month' => $dayOfMonth,
            'schedule_month' => $startMonth,
            'schedule_start_month' => $startMonth,
        ]);
    }

    public function halfYearly(int $dayOfMonth = 1, Month $startMonth = Month::JANUARY): static
    {
        return $this->state([
            'schedule_frequency' => ScheduleFrequency::HALF_YEARLY,
            'schedule_day_of_month' => $dayOfMonth,
            'schedule_month' => $startMonth,
            'schedule_start_month' => $startMonth,
        ]);
    }

    public function yearly(int $dayOfMonth = 1, Month $month = Month::JANUARY): static
    {
        return $this->state([
            'schedule_frequency' => ScheduleFrequency::YEARLY,
            'schedule_day_of_month' => $dayOfMonth,
            'schedule_month' => $month,
        ]);
    }

    public function cron(string $expression = '0 9 * * *'): static
    {
        return $this->state([
            'schedule_frequency' => ScheduleFrequency::CRON,
            'cron' => $expression,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(['enabled' => false]);
    }
}
