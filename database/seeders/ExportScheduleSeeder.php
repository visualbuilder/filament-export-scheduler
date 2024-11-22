<?php

namespace VisualBuilder\ExportScheduler\Database\Seeders;

use Filament\Actions\Exports\Enums\ExportFormat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use VisualBuilder\ExportScheduler\Enums\DayOfWeek;
use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;
use VisualBuilder\ExportScheduler\Filament\Exporters\UserExporter;
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
        DB::table('export_schedules')->insert($this->getData());
    }

    public function getData()
    {
        return [
            // DAILY Export Schedule
            [
                'name'                  => 'User Export Daily',
                'exporter'              => UserExporter::class,
                'schedule_frequency'    => ScheduleFrequency::DAILY,
                'cron'                  => null,
                'formats'               => json_encode([ExportFormat::Csv]),
                'schedule_time'         => '03:00:00', // Runs daily at 3:00 AM
                'schedule_month'        => null,
                'schedule_day_of_week'  => null,
                'schedule_day_of_month' => null,
                'schedule_start_month'  => null,
                'columns'               => json_encode([
                    ['name' => 'id', 'label' => 'ID'],
                    ['name' => 'email', 'label' => 'Email'],
                    ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
                ]),
                'owner_id'              => 1, // Replace with the actual owner ID
                'owner_type'            => User::class,
            ],

/**

            // WEEKLY Export Schedule (Monday)
            [
                'name'                  => 'User Export Weekly (Monday)',
                'exporter'              => UserExporter::class,
                'schedule_frequency'    => ScheduleFrequency::WEEKLY,
                'cron'                  => null,
                'formats'               => json_encode([ExportFormat::Csv]),
                'schedule_time'         => '04:00:00', // Runs weekly at 4:00 AM on Monday
                'schedule_month'        => null,
                'schedule_day_of_week'  => DayOfWeek::MONDAY,
                'schedule_day_of_month' => null,
                'schedule_start_month'  => null,
                'columns'               => json_encode([
                    ['name' => 'id', 'label' => 'ID'],
                    ['name' => 'email', 'label' => 'Email'],
                    ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
                ]),
                'owner_id'              => 1, // Replace with the actual owner ID
                'owner_type'            => User::class,
            ],

            // MONTHLY Export Schedule (Day 15)
            [
                'name'                  => 'User Export Monthly (Day 15)',
                'exporter'              => UserExporter::class,
                'schedule_frequency'    => ScheduleFrequency::MONTHLY,
                'cron'                  => null,
                'formats'               => json_encode([ExportFormat::Csv]),
                'schedule_time'         => '05:00:00', // Runs monthly at 5:00 AM on the 15th day
                'schedule_month'        => null,
                'schedule_day_of_week'  => null,
                'schedule_day_of_month' => 15,
                'schedule_start_month'  => null,
                'columns'               => json_encode([
                    ['name' => 'id', 'label' => 'ID'],
                    ['name' => 'email', 'label' => 'Email'],
                    ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
                ]),
                'owner_id'              => 1, // Replace with the actual owner ID
                'owner_type'            => User::class,
            ],


            // MONTHLY Export Schedule (Last Day of Month)
            [
                'name'                  => 'User Export Monthly (Last Day)',
                'exporter'              => UserExporter::class,
                'schedule_frequency'    => ScheduleFrequency::MONTHLY,
                'cron'                  => null,
                'formats'               => json_encode([ExportFormat::Csv]),
                'schedule_time'         => '06:00:00', // Runs monthly at 6:00 AM on the last day
                'schedule_month'        => null,
                'schedule_day_of_week'  => null,
                'schedule_day_of_month' => -1, // -1 represents the last day
                'schedule_start_month'  => null,
                'columns'               => json_encode([
                    ['name' => 'id', 'label' => 'ID'],
                    ['name' => 'email', 'label' => 'Email'],
                    ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
                ]),
                'owner_id'              => 1, // Replace with the actual owner ID
                'owner_type'            => User::class,
            ],

            // YEARLY Export Schedule
            [
                'name'                  => 'User Export Yearly',
                'exporter'              => UserExporter::class,
                'schedule_frequency'    => ScheduleFrequency::YEARLY,
                'cron'                  => null,
                'formats'               => json_encode([ExportFormat::Csv]),
                'schedule_time'         => '07:00:00', // Runs yearly at 7:00 AM on a specific date (implied, depends on your logic)
                'schedule_month'        => 1,
                'schedule_day_of_week'  => null,
                'schedule_day_of_month' => 1, // Runs on January 1st of every year
                'schedule_start_month'  => 1, // January
                'columns'               => json_encode([
                    ['name' => 'id', 'label' => 'ID'],
                    ['name' => 'email', 'label' => 'Email'],
                    ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
                ]),
                'owner_id'              => 1, // Replace with the actual owner ID
                'owner_type'            => User::class,
            ],


            // QUARTERLY Export Schedule (Starting in January, on the 10th day)
            [
                'name'                  => 'User Export Quarterly (Jan 10)',
                'exporter'              => UserExporter::class,
                'schedule_frequency'    => ScheduleFrequency::QUARTERLY,
                'cron'                  => null,
                'formats'               => json_encode([ExportFormat::Csv]),
                'schedule_time'         => '08:00:00', // Runs quarterly at 8:00 AM
                'schedule_month'        => 1,
                'schedule_day_of_week'  => null,
                'schedule_day_of_month' => 10,
                'schedule_start_month'  => 1, // January
                'columns'               => json_encode([
                    ['name' => 'id', 'label' => 'ID'],
                    ['name' => 'email', 'label' => 'Email'],
                    ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
                ]),
                'owner_id'              => 1, // Replace with the actual owner ID
                'owner_type'            => User::class,
            ],

            // HALF_YEARLY Export Schedule (Starting in July, on the 1st day)
            [
                'name'                  => 'User Export Half-Yearly (Jul 1)',
                'exporter'              => UserExporter::class,
                'schedule_frequency'    => ScheduleFrequency::HALF_YEARLY,
                'cron'                  => null,
                'formats'               => json_encode([ExportFormat::Csv]),
                'schedule_time'         => '09:00:00', // Runs half-yearly at 9:00 AM
                'schedule_month'        => null,
                'schedule_day_of_week'  => null,
                'schedule_day_of_month' => 1,
                'schedule_start_month'  => 7,          // July
                'columns'               => json_encode([
                    ['name' => 'id', 'label' => 'ID'],
                    ['name' => 'email', 'label' => 'Email'],
                    ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
                ]),
                'owner_id'              => 1, // Replace with the actual owner ID
                'owner_type'            => User::class,
            ],

            // Custom Cron: Every weekday at 10:30 AM
            [
                'name'                  => 'User Export Weekdays 10:30 AM',
                'exporter'              => UserExporter::class,
                'schedule_frequency'    => ScheduleFrequency::CRON,
                'cron'                  => '30 10 * * 1-5', // Every weekday at 10:30 AM
                'formats'               => json_encode([ExportFormat::Csv]),
                'schedule_time'         => '09:00:00', //ignored when cron
                'schedule_month'        => null,
                'schedule_day_of_week'  => null,
                'schedule_day_of_month' => null,
                'schedule_start_month'  => null,          // July
                'columns'               => json_encode([
                    ['name' => 'id', 'label' => 'ID'],
                    ['name' => 'email', 'label' => 'Email'],
                    ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
                ]),
                'owner_id'              => 1,
                'owner_type'            => User::class,
            ],


            // Custom Cron: At 2:00 AM on the first day of every month, only in March, June, September, and December
            [
                'name'                  => 'User Export Quarter Ends 2:00 AM',
                'exporter'              => UserExporter::class,
                'schedule_frequency'    => ScheduleFrequency::CRON,
                'cron'                  => '0 2 1 3,6,9,12 *', // At 2:00 AM on the 1st of Mar, Jun, Sep, Dec
                'formats'               => json_encode([ExportFormat::Csv]),
                'schedule_month'        => null,
                'schedule_time'         => '09:00:00',//ignored when cron
                'schedule_day_of_week'  => null,
                'schedule_day_of_month' => null,
                'schedule_start_month'  => null,          // July
                'columns'               => json_encode([
                    ['name' => 'id', 'label' => 'ID'],
                    ['name' => 'email', 'label' => 'Email'],
                    ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
                ]),
                'owner_id'              => 1,
                'owner_type'            => User::class,
            ],

            [
                'name'                  => 'User Export Leap Year Test',
                'exporter'              => UserExporter::class,
                'schedule_frequency'    => ScheduleFrequency::YEARLY,
                'cron'                  => null,
                'formats'               => json_encode([ExportFormat::Csv]),
                'schedule_month'        => 2,
                'schedule_time'         => '10:00:00', // Runs yearly at 10:00 AM
                'schedule_day_of_week'  => null,
                'schedule_day_of_month' => 29,            // Runs on Feb 29th
                'schedule_start_month'  => null,          // July
                'columns'               => json_encode([
                    ['name' => 'id', 'label' => 'ID'],
                    ['name' => 'email', 'label' => 'Email'],
                    ['name' => 'created_at', 'label' => 'Created At', 'formatter' => 'datetime'],
                ]),
                'owner_id'              => 1, // Replace with actual ID
                'owner_type'            => User::class,
            ],


 * */
        ];

    }

}
