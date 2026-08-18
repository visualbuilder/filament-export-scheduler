<?php

use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;
use Visualbuilder\ExportScheduler\Tests\Exporters\DocumentOwnerExporter;
use Visualbuilder\ExportScheduler\Tests\Models\Contact;
use Visualbuilder\ExportScheduler\Tests\Models\Document;
use Visualbuilder\ExportScheduler\Tests\Models\Organisation;

it('detects chained datetime attributes', function () {
    $contact = Contact::create(['full_name' => 'John Doe']);
    $org = Organisation::create(['name' => 'Acme', 'primary_contact_id' => $contact->id]);

    Document::create(['title' => 'Doc', 'owner_type' => Organisation::class, 'owner_id' => $org->id]);

    $report = CustomReport::create([
        'name' => 'Document Export',
        'exporter' => DocumentOwnerExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'title', 'label' => 'Title'],
            ['name' => 'owner.created_at', 'label' => 'Owner Created At'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'filters' => [
            'attributes' => [
                [
                    'column' => 'owner.created_at',
                    'operator' => 'since',
                    'value' => now()->subDay()->toDateString(),
                    'condition' => 'and',
                ],
            ],
        ],
    ]);

    expect((new ScheduledExporter($report))->run())->toBeTrue();
});
