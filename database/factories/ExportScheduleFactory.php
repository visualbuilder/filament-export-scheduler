<?php

namespace Visualbuilder\ExportScheduler\Database\Factories;

use Filament\Actions\Exports\Enums\ExportFormat;
use Illuminate\Database\Eloquent\Factories\Factory;
use Visualbuilder\ExportScheduler\Enums\DateRange;
use Visualbuilder\ExportScheduler\Enums\ReportType;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Models\ExportSchedule;
use Visualbuilder\ExportScheduler\Tests\Models\User;


class ExportScheduleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ExportSchedule::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name'                   => $this->faker->sentence(3),
            'report_type'            => ReportType::EXPORTER,
            'exporter'               => UserExporter::class,
            'columns'                => json_encode([
                [
                    'name'  => 'id',
                    'label' => 'ID',
                ],
                [
                    'name'  => 'email',
                    'label' => 'Email',
                ],
                [
                    'name'      => 'created_at',
                    'label'     => 'Date Added',
                    'formatter' => 'long_date',
                ],

            ]),
            'schedule_frequency'     => $this->faker->randomElement(ScheduleFrequency::values()),
            'schedule_timezone'      => $this->faker->timezone,
            'date_range'             => $this->faker->randomElement(DateRange::values()),
            'schedule_start_month'   => $this->faker->numberBetween(1, 12),
            'schedule_day_of_week'   => $this->faker->numberBetween(0, 6),
            'formats'                => [ExportFormat::Xlsx],
            'owner_id'               => User::factory(),
            'owner_type'             => User::class,
            'cron'                   => null,
            'last_run_at'            => null,
            'last_successful_run_at' => null,

        ];
    }
}
