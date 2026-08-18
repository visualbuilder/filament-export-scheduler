<?php

use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\ViewCustomReport;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Tests\Exporters\DocumentExporter;
use Visualbuilder\ExportScheduler\Tests\Exporters\LinkedDocumentExporter;
use Visualbuilder\ExportScheduler\Tests\Models\Document;
use Visualbuilder\ExportScheduler\Tests\Models\User;

use function Pest\Livewire\livewire;

function makeScheduleWithExporter(string $exporterClass, ?array $columns = null): CustomReport
{
    return CustomReport::create([
        'name' => 'Test Report',
        'report_type' => 'exporter',
        'exporter' => $exporterClass,
        'columns' => $columns,
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
}

it('adds url to root record column when exporter implements HasLinkedColumns', function () {
    $document = Document::create(['title' => 'Test Doc']);

    $schedule = makeScheduleWithExporter(LinkedDocumentExporter::class, [
        ['name' => 'id', 'label' => 'ID'],
        ['name' => 'title', 'label' => 'Title'],
    ]);

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->assertOk()
        ->assertSee('/documents/' . $document->id);
});

it('adds url to relation column when resolver returns one', function () {
    $ownerA = User::create(['name' => 'Owner A', 'email' => 'ownerA@domain.com', 'password' => 'password']);
    $ownerB = User::create(['name' => 'Owner B', 'email' => 'ownerB@domain.com', 'password' => 'password']);

    $docA = Document::create(['title' => 'Doc A', 'owner_id' => $ownerA->id, 'owner_type' => User::class]);
    $docB = Document::create(['title' => 'Doc B', 'owner_id' => $ownerB->id, 'owner_type' => User::class]);

    $schedule = makeScheduleWithExporter(LinkedDocumentExporter::class, [
        ['name' => 'title', 'label' => 'Title'],
        ['name' => 'owner.created_at', 'label' => 'Owner Created At'],
    ]);

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->assertOk()
        ->assertSee('/users/' . $ownerA->id)
        ->assertSee('/users/' . $ownerB->id);
});

it('returns null from resolver when related record does not exist', function () {
    Document::create(['title' => 'Doc No Owner']); // No owner set

    $schedule = makeScheduleWithExporter(LinkedDocumentExporter::class, [
        ['name' => 'title', 'label' => 'Title'],
        ['name' => 'owner.created_at', 'label' => 'Owner Created At'],
    ]);

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->assertOk()
        ->assertSee('Doc No Owner');
});

it('does not fire resolver for columns not selected in the report', function () {
    $document = Document::create(['title' => 'Test Doc']);

    // Select only the title column, not the id column that has a resolver
    $schedule = makeScheduleWithExporter(LinkedDocumentExporter::class, [
        ['name' => 'title', 'label' => 'Title'],
    ]);

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->assertOk()
        ->assertSee('Test Doc')
        // The document URL should not appear because the id column wasn't selected
        ->assertDontSee('/documents/' . $document->id);
});

it('does not add urls when exporter does not implement HasLinkedColumns', function () {
    $document = Document::create(['title' => 'Test Doc']);

    $schedule = makeScheduleWithExporter(DocumentExporter::class, [
        ['name' => 'id', 'label' => 'ID'],
        ['name' => 'title', 'label' => 'Title'],
    ]);

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->assertOk()
        ->assertSee('Test Doc')
        // URL pattern should not appear
        ->assertDontSee('/documents/');
});

it('does not add urls for SQL query reports', function () {
    User::create(['name' => 'Test User', 'email' => 'test@domain.com', 'password' => 'password']);

    $schedule = CustomReport::create([
        'name' => 'SQL Report',
        'report_type' => 'sql_query',
        'sql_query' => 'SELECT id, email FROM users',
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    livewire(ViewCustomReport::class, ['record' => $schedule->getKey()])
        ->assertOk()
        ->assertSee('test@domain.com');
});
