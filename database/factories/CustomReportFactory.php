<?php

namespace Visualbuilder\ExportScheduler\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Visualbuilder\ExportScheduler\Enums\ReportType;
use Visualbuilder\ExportScheduler\Enums\ReportVisibility;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Tests\Models\User;

class CustomReportFactory extends Factory
{
    protected $model = CustomReport::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'report_type' => ReportType::EXPORTER,
            'exporter' => UserExporter::class,
            // Plain arrays, not json_encode(): both columns are cast to `array`, so a
            // pre-encoded string would be encoded a second time on save.
            'columns' => [
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
                    'label' => 'Date Added',
                    'formatter' => 'long_date',
                ],
            ],
            'filters' => null,
            'owner_id' => User::factory(),
            'owner_type' => User::class,
            'visibility' => ReportVisibility::OWNER,
            'visible_to_type' => null,
            'visible_to_ids' => null,
        ];
    }
}
