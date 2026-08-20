<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\ViewCustomReport;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Tests\Exporters\ThrowingLinkDocumentExporter;
use Visualbuilder\ExportScheduler\Tests\Models\Document;

use function Pest\Livewire\livewire;

function makeThrowingLinkReport(): CustomReport
{
    return CustomReport::create([
        'name' => 'Throwing Link Report',
        'report_type' => 'exporter',
        'exporter' => ThrowingLinkDocumentExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'title', 'label' => 'Title'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
}

/**
 * Reach the protected getExporterRows() on a mounted page so the __links payload
 * can be asserted directly, rather than only inferring it from the rendered HTML.
 */
function exporterRowsFor(CustomReport $report): Collection
{
    $page = new ViewCustomReport;
    $page->mount($report->getKey());

    $method = new ReflectionMethod($page, 'getExporterRows');
    $method->setAccessible(true);

    return $method->invoke($page);
}

it('does not blank the whole report when one link resolver throws', function () {
    $ok = Document::create(['title' => 'Fine']);
    $boom = Document::create(['title' => ThrowingLinkDocumentExporter::$failForTitle]);

    $report = makeThrowingLinkReport();

    $rows = exporterRowsFor($report);

    // Both rows still returned — the failing resolver did not empty the result.
    expect($rows)->toHaveCount(2);

    // The healthy row keeps its link; the failing row's link is null.
    expect($rows[$ok->getKey()]['__links']['id'])->toBe("/documents/{$ok->id}");
    expect($rows[$boom->getKey()]['__links']['id'])->toBeNull();
});

it('logs the resolver failure with context', function () {
    Log::spy();

    Document::create(['title' => 'Fine']);
    $boom = Document::create(['title' => ThrowingLinkDocumentExporter::$failForTitle]);

    $report = makeThrowingLinkReport();

    exporterRowsFor($report);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($report, $boom): bool {
            return str_contains($message, 'link resolver')
                && $context['report_id'] === $report->getKey()
                && $context['exporter'] === ThrowingLinkDocumentExporter::class
                && $context['column'] === 'id'
                && $context['record_id'] === $boom->getKey()
                && array_key_exists('exception', $context);
        });
});

it('still renders every row in the viewer when a link resolver throws', function () {
    Document::create(['title' => 'Fine']);
    Document::create(['title' => ThrowingLinkDocumentExporter::$failForTitle]);

    $report = makeThrowingLinkReport();

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->assertOk()
        ->assertSee('Fine')
        ->assertSee(ThrowingLinkDocumentExporter::$failForTitle);
});
