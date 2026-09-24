<?php

use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\ViewCustomReport;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;
use Visualbuilder\ExportScheduler\Tests\Exporters\DocumentExporter;
use Visualbuilder\ExportScheduler\Tests\Models\Document;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Notification::fake();
});

/**
 * @param  array<int, array{name: string, label?: string}>  $columns
 */
function makeStaleColumnReport(string $exporter, array $columns): CustomReport
{
    return CustomReport::create([
        'name' => 'Stale Column Report',
        'report_type' => 'exporter',
        'exporter' => $exporter,
        'columns' => $columns,
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);
}

/**
 * @return array<string, string>
 */
function scheduledColumnMap(ScheduledExporter $exporter): array
{
    return (new ReflectionProperty($exporter, 'columnMap'))->getValue($exporter);
}

it('leaves a stale column out of the column map and logs it with the exporter', function () {
    Log::spy();

    $report = makeStaleColumnReport(UserExporter::class, [
        ['name' => 'id', 'label' => 'ID'],
        ['name' => 'owner_name', 'label' => 'Owner'],
    ]);

    expect($report->getExportableColumnMap('scheduled_run', 7))->toBe(['id' => 'ID']);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'was left out')
            && $context['report_id'] === $report->getKey()
            && $context['report_name'] === 'Stale Column Report'
            && $context['exporter'] === UserExporter::class
            && $context['stale_columns'] === ['owner_name' => 'Owner']
            && $context['schedule_id'] === 7
            && $context['source'] === 'scheduled_run');
});

it('falls back to the exporter columns and warns when every saved column is stale', function () {
    Log::spy();

    $report = makeStaleColumnReport(UserExporter::class, [
        ['name' => 'owner_name', 'label' => 'Owner'],
        ['name' => 'account_managers', 'label' => 'Account Managers'],
    ]);

    expect($report->getExportableColumnMap('viewer'))
        ->toBe(['id' => 'Id', 'email' => 'Email', 'created_at' => 'Date Added']);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'default columns instead')
            && $context['exporter'] === UserExporter::class
            && array_keys($context['stale_columns']) === ['owner_name', 'account_managers']);
});

it('logs nothing when every saved column still exists', function () {
    Log::spy();

    $report = makeStaleColumnReport(UserExporter::class, [
        ['name' => 'id', 'label' => 'ID'],
        ['name' => 'email', 'label' => 'Email'],
    ]);

    expect($report->getExportableColumnMap('scheduled_run'))->toBe(['id' => 'ID', 'email' => 'Email']);

    Log::shouldNotHaveReceived('warning');
});

it('falls back to the exporter columns when none are saved', function () {
    $report = makeStaleColumnReport(UserExporter::class, []);

    expect($report->getExportableColumnMap('viewer'))
        ->toBe(['id' => 'Id', 'email' => 'Email', 'created_at' => 'Date Added']);
});

it('returns no columns without the fallback when every saved column is stale', function () {
    Log::spy();

    $report = makeStaleColumnReport(UserExporter::class, [
        ['name' => 'owner_name', 'label' => 'Owner'],
    ]);

    expect($report->getExportableColumnMap('scheduled_run', fallbackToExporterColumns: false))->toBe([]);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'the export will be empty')
            && $context['stale_columns'] === ['owner_name' => 'Owner']);
});

it('returns no columns without the fallback when none are saved', function () {
    $report = makeStaleColumnReport(UserExporter::class, []);

    expect($report->getExportableColumnMap('scheduled_run', fallbackToExporterColumns: false))->toBe([]);
});

it('exports every row of a scheduled run despite a stale column', function () {
    $report = makeStaleColumnReport(UserExporter::class, [
        ['name' => 'id', 'label' => 'ID'],
        ['name' => 'email', 'label' => 'Email'],
        ['name' => 'owner_name', 'label' => 'Owner'],
    ]);

    $exporter = new ScheduledExporter($report);

    expect($exporter->run())->toBeTrue();

    // Before the guard every row threw on the stale key and was skipped, leaving
    // successful_rows at 0.
    $export = Export::query()->findOrFail($exporter->getExport()->getKey());

    expect($export->total_rows)->toBeGreaterThan(0)
        ->and($export->successful_rows)->toBe($export->total_rows);
});

it('sends an empty export rather than the exporter columns when every saved column is stale', function () {
    Log::spy();

    $report = makeStaleColumnReport(UserExporter::class, [
        ['name' => 'owner_name', 'label' => 'Owner'],
    ]);

    $exporter = new ScheduledExporter($report);

    expect($exporter->run())->toBeTrue()
        ->and($exporter->getExport())->not->toBeNull()
        ->and(scheduledColumnMap($exporter))->toBe([]);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'the export will be empty')
            && $context['source'] === 'scheduled_run');

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'sending an empty export')
            && $context['report_id'] === $report->getKey());
});

it('sends an empty export and logs it when the report has no saved columns', function () {
    Log::spy();

    $report = makeStaleColumnReport(UserExporter::class, []);

    $exporter = new ScheduledExporter($report);

    expect($exporter->run())->toBeTrue()
        ->and($exporter->getExport())->not->toBeNull()
        ->and(scheduledColumnMap($exporter))->toBe([]);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'sending an empty export')
            && $context['report_id'] === $report->getKey());
});

it('shows the exporter columns in the report viewer when every saved column is stale', function () {
    Document::create(['title' => 'Quarterly']);

    $report = makeStaleColumnReport(DocumentExporter::class, [
        ['name' => 'summary', 'label' => 'Stale Summary Column'],
    ]);

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->assertOk()
        ->assertSee('Quarterly')
        ->assertDontSee('Stale Summary Column');
});

it('logs a stale column in the report viewer at most once an hour', function () {
    Log::spy();

    $report = makeStaleColumnReport(UserExporter::class, [
        ['name' => 'id', 'label' => 'ID'],
        ['name' => 'owner_name', 'label' => 'Owner'],
    ]);

    $report->getExportableColumnMap('viewer');
    $report->getExportableColumnMap('viewer');

    Log::shouldHaveReceived('warning')->once();

    $this->travel(61)->minutes();

    $report->getExportableColumnMap('viewer');

    Log::shouldHaveReceived('warning')->twice();
});

it('still drops and logs a stale column in the report viewer when the cache is down', function () {
    Log::spy();

    Cache::shouldReceive('add')->andThrow(new RuntimeException('Connection refused'));

    $report = makeStaleColumnReport(UserExporter::class, [
        ['name' => 'id', 'label' => 'ID'],
        ['name' => 'owner_name', 'label' => 'Owner'],
    ]);

    expect($report->getExportableColumnMap('viewer'))->toBe(['id' => 'ID']);

    Log::shouldHaveReceived('warning')->once();
});

it('logs again in the report viewer when a different column goes stale', function () {
    Log::spy();

    $report = makeStaleColumnReport(UserExporter::class, [
        ['name' => 'id', 'label' => 'ID'],
        ['name' => 'owner_name', 'label' => 'Owner'],
    ]);

    $report->getExportableColumnMap('viewer');

    $report->update(['columns' => [
        ['name' => 'id', 'label' => 'ID'],
        ['name' => 'account_managers', 'label' => 'Account Managers'],
    ]]);

    $report->getExportableColumnMap('viewer');

    Log::shouldHaveReceived('warning')->twice();
});

it('logs a stale column on every scheduled run', function () {
    Log::spy();

    $report = makeStaleColumnReport(UserExporter::class, [
        ['name' => 'id', 'label' => 'ID'],
        ['name' => 'owner_name', 'label' => 'Owner'],
    ]);

    $report->getExportableColumnMap('scheduled_run');
    $report->getExportableColumnMap('scheduled_run');

    Log::shouldHaveReceived('warning')->twice();
});

it('still drops a stale column in the report viewer', function () {
    Document::create(['title' => 'Quarterly']);

    $report = makeStaleColumnReport(DocumentExporter::class, [
        ['name' => 'title', 'label' => 'Title'],
        ['name' => 'summary', 'label' => 'Stale Summary Column'],
    ]);

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->assertOk()
        ->assertSee('Quarterly')
        ->assertDontSee('Stale Summary Column');
});

it('tells the user the columns need updating instead of downloading an empty file', function () {
    $report = makeStaleColumnReport(UserExporter::class, [
        ['name' => 'owner_name', 'label' => 'Owner'],
    ]);

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->callAction('download', ['format' => ExportFormat::Csv->value])
        ->assertNotified('Report columns need updating');

    expect(Export::query()->count())->toBe(0);
});

it('downloads the remaining columns when only some saved columns are stale', function () {
    $report = makeStaleColumnReport(UserExporter::class, [
        ['name' => 'id', 'label' => 'ID'],
        ['name' => 'owner_name', 'label' => 'Owner'],
    ]);

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->callAction('download', ['format' => ExportFormat::Csv->value])
        ->assertNotified('Some columns were left out');

    expect(Export::query()->count())->toBe(1);
});

it('italicises each left-out column and escapes its label', function () {
    $report = makeStaleColumnReport(UserExporter::class, [
        ['name' => 'id', 'label' => 'ID'],
        ['name' => 'owner_name', 'label' => 'Owner'],
        ['name' => 'account_managers', 'label' => 'Managers <b>'],
    ]);

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->callAction('download', ['format' => ExportFormat::Csv->value]);

    // Read the sent notification the way Filament's own assertNotified() does.
    $notifications = new Notifications;
    $notifications->mount();

    $body = $notifications->notifications
        ->first(fn (FilamentNotification $notification): bool => $notification->getTitle() === 'Some columns were left out')
        ->getBody();

    expect($body)->toContain('<em>Owner</em>, <em>Managers &lt;b&gt;</em>');
});

it('does not warn about left-out columns when none are stale', function () {
    $report = makeStaleColumnReport(UserExporter::class, [
        ['name' => 'id', 'label' => 'ID'],
    ]);

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->callAction('download', ['format' => ExportFormat::Csv->value])
        ->assertNotNotified('Some columns were left out');
});

it('lists the stale columns by label', function () {
    $report = makeStaleColumnReport(UserExporter::class, [
        ['name' => 'id', 'label' => 'ID'],
        ['name' => 'owner_name', 'label' => 'Owner'],
        ['name' => 'account_managers'],
    ]);

    expect($report->getStaleColumns())->toBe(['owner_name' => 'Owner', 'account_managers' => 'account_managers']);
});

it('only counts a report as all stale when it has saved columns', function (array $columns, bool $expected) {
    expect(makeStaleColumnReport(UserExporter::class, $columns)->hasOnlyStaleColumns())->toBe($expected);
})->with([
    'every column stale' => [[['name' => 'owner_name', 'label' => 'Owner']], true],
    'some columns stale' => [[['name' => 'id', 'label' => 'ID'], ['name' => 'owner_name', 'label' => 'Owner']], false],
    'no columns stale' => [[['name' => 'id', 'label' => 'ID']], false],
    'no columns saved' => [[], false],
]);
