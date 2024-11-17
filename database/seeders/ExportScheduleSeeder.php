<?php

namespace VisualBuilder\ExportScheduler\Database\Seeders;

use Filament\Actions\Exports\Enums\ExportFormat;
use Illuminate\Database\Seeder;
use VisualBuilder\ExportScheduler\Enums\DateRange;
use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;
use VisualBuilder\ExportScheduler\Exporters\UserExporter;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;
use VisualBuilder\ExportScheduler\Tests\Models\User;

// Import the Invoice model

class ExportScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        ExportSchedule::create([
            'name' => 'Invoice Export',
            'exporter' => UserExporter::class,
            'schedule_frequency' => ScheduleFrequency::MONTHLY,
            'formats' => [ExportFormat::Csv, ExportFormat::Xlsx],
            'schedule_time' => '02:00:00',
            'schedule_day_of_month' => 1,
            'schedule_timezone' => 'Europe/London',
            'date_range' => DateRange::LAST_MONTH,
            'owner_id' => 1,
            'owner_type' => User::class,
            'columns' => json_encode([
                [
                    'name' => 'id',
                    'label' => 'ID',
                ],
                [
                    'name' => 'email',
                    'label' => 'Email',
                ],

                [
                    'name' => 'created_at',
                    'label' => 'Created At',
                    'formatter' => 'datetime',
                ],
            ]),

        ]);
    }
}
