<?php

use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Models\ExportSchedule;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;
use Visualbuilder\ExportScheduler\Tests\Exporters\DocumentExporter;
use Visualbuilder\ExportScheduler\Tests\Models\Contact;
use Visualbuilder\ExportScheduler\Tests\Models\Document;
use Visualbuilder\ExportScheduler\Tests\Models\Organisation;

it('applies attribute filter on nested MorphTo relation', function () {
    $contact = Contact::create(['full_name' => 'John Doe']);
    $org = Organisation::create(['name' => 'Acme', 'primary_contact_id' => $contact->id]);
    Document::create(['title' => 'Doc', 'owner_type' => Organisation::class, 'owner_id' => $org->id]);

    $schedule = ExportSchedule::create([
        'name' => 'Document Export',
        'exporter' => DocumentExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => now()->toTimeString(),
        'next_run_at' => now(),
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'title', 'label' => 'Title'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'filters' => [
            'attributes' => [
                [
                    'column' => 'owner.primary_contact.name',
                    'value' => 'John',
                    'operator' => 'like',
                    'condition' => 'and',
                ],
            ],
        ],
        'formats' => ["xlsx"]
    ]);

    $exporter = new ScheduledExporter($schedule);
    expect($exporter->run())->toBeTrue();
});
