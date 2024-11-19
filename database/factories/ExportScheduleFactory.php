<?php

namespace VisualBuilder\ExportScheduler\Database\Factories;

use Filament\Actions\Exports\Enums\ExportFormat;
use Illuminate\Database\Eloquent\Factories\Factory;
use VisualBuilder\ExportScheduler\Enums\DateRange;
use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;
use VisualBuilder\ExportScheduler\Filament\Exporters\UserExporter;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;
use VisualBuilder\ExportScheduler\Tests\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\VisualBuilder\ExportScheduler\Models\ExportSchedule>
 */
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
            'formats'                => [ExportFormat::Xlsx],
            'owner_id'               => User::factory(),
            'owner_type'             => User::class,
            'cron'                   => null,
            'last_run_at'            => null,
            'last_successful_run_at' => null,

        ];
    }
}
