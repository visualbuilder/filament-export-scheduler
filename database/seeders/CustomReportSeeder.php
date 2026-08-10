<?php

namespace Visualbuilder\ExportScheduler\Database\Seeders;

use Filament\Actions\Exports\Enums\ExportFormat;
use Illuminate\Database\Seeder;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;
use Visualbuilder\ExportScheduler\Tests\Models\User;

class CustomReportSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'user@domain.com'],
            ['name' => 'Admin', 'password' => 'password']
        );

        $report = CustomReport::create([
            'name' => 'User Export Daily',
            'exporter' => UserExporter::class,
            'columns' => json_encode([
                ['name' => 'id', 'label' => 'ID'],
                ['name' => 'email', 'label' => 'Email'],
                ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
            ]),
            'owner_id' => $user->id,
            'owner_type' => User::class,
            'visibility' => 'owner',
        ]);

        ScheduledReport::create([
            'custom_report_id' => $report->id,
            'schedule_frequency' => ScheduleFrequency::DAILY,
            'schedule_time' => '15:00:00',
            'formats' => [ExportFormat::Csv],
            'recipient_id' => $user->id,
            'recipient_type' => User::class,
            'cc' => [],
            'enabled' => true,
            'send_empty_report' => true,
        ]);
    }
}
