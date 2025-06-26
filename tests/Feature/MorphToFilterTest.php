<?php

use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;
use VisualBuilder\ExportScheduler\Services\ScheduledExporter;
use VisualBuilder\ExportScheduler\Tests\Exporters\DocumentExporter;
use VisualBuilder\ExportScheduler\Tests\Models\Contact;
use VisualBuilder\ExportScheduler\Tests\Models\Document;
use VisualBuilder\ExportScheduler\Tests\Models\Organisation;

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
                    'column' => 'owner.primary_contact.full_name',
                    'value' => 'John',
                    'operator' => 'like',
                    'condition' => 'and',
                ],
            ],
        ],
    ]);

    $exporter = new ScheduledExporter($schedule);
    expect($exporter->run())->toBeTrue();
});
