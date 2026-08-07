<?php

use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Visualbuilder\ExportScheduler\Enums\ReportType;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Filament\Resources\ExportScheduleResource\Pages\ListExportSchedules;
use Visualbuilder\ExportScheduler\Filament\Resources\ExportScheduleResource\Pages\ViewExportSchedule;
use Visualbuilder\ExportScheduler\Jobs\CreateSqlQueryXlsxFile;
use Visualbuilder\ExportScheduler\Jobs\ScheduledExportCompletion;
use Visualbuilder\ExportScheduler\Models\ExportSchedule;
use Visualbuilder\ExportScheduler\Notifications\ScheduledExportCompleteNotification;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;
use Visualbuilder\ExportScheduler\Tests\Models\User;

use function Pest\Livewire\livewire;

function makeDownloadableSchedule(array $overrides = []): ExportSchedule
{
    return ExportSchedule::create(array_merge([
        'name' => 'Downloadable Report',
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '08:00',
        'schedule_timezone' => 'UTC',
        'formats' => ['csv'],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'enabled' => true,
    ], $overrides));
}

it('runs the export for the user who asked for it', function () {
    $owner = User::create(['name' => 'Owner', 'email' => 'owner@domain.com', 'password' => 'password']);

    $schedule = makeDownloadableSchedule([
        'owner_id' => $owner->id,
        'owner_type' => User::class,
    ]);

    livewire(ViewExportSchedule::class, ['record' => $schedule->getKey()])
        ->callAction('download', ['format' => 'csv'])
        ->assertHasNoActionErrors();

    expect(Export::count())->toBe(1);

    // The download route only serves an export to the user it belongs to.
    expect(Export::first()->user_id)->toBe(auth()->id());
    expect($schedule->refresh()->last_run_at)->toBeNull();
});

it('defaults the format to the first one the schedule is configured with', function () {
    $schedule = makeDownloadableSchedule(['formats' => ['xlsx', 'csv']]);

    livewire(ViewExportSchedule::class, ['record' => $schedule->getKey()])
        ->mountAction('download')
        ->assertActionDataSet(['format' => 'xlsx']);
});

it('creates the xlsx file when xlsx is chosen', function () {
    Bus::fake();

    $schedule = makeDownloadableSchedule();

    livewire(ViewExportSchedule::class, ['record' => $schedule->getKey()])
        ->callAction('download', ['format' => 'xlsx']);

    Bus::assertChained([
        Illuminate\Bus\ChainedBatch::class,
        CreateXlsxFile::class,
        ScheduledExportCompletion::class,
    ]);
});

it('does not create the xlsx file when csv is chosen', function () {
    Bus::fake();

    $schedule = makeDownloadableSchedule(['formats' => ['xlsx']]);

    livewire(ViewExportSchedule::class, ['record' => $schedule->getKey()])
        ->callAction('download', ['format' => 'csv']);

    Bus::assertChained([
        Illuminate\Bus\ChainedBatch::class,
        ScheduledExportCompletion::class,
    ]);
});

it('builds the xlsx file for a sql query report without an exporter class', function () {
    Bus::fake();

    $schedule = makeDownloadableSchedule([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => 'SELECT id, email FROM users',
    ]);

    livewire(ViewExportSchedule::class, ['record' => $schedule->getKey()])
        ->callAction('download', ['format' => 'xlsx']);

    Bus::assertChained([
        Visualbuilder\ExportScheduler\Jobs\ExportSqlQuery::class,
        CreateSqlQueryXlsxFile::class,
        ScheduledExportCompletion::class,
    ]);
});

it('writes the xlsx file end to end for an exporter report', function () {
    $schedule = makeDownloadableSchedule();

    livewire(ViewExportSchedule::class, ['record' => $schedule->getKey()])
        ->callAction('download', ['format' => 'xlsx']);

    $export = Export::first();

    // A sync queue has run the whole chain, so the file is on disk and ready to download.
    expect($export->completed_at)->not->toBeNull();
    expect($export->getFileDisk()->exists($export->getFileDirectory() . DIRECTORY_SEPARATOR . $export->file_name . '.xlsx'))->toBeTrue();
});

it('writes the xlsx file end to end for a sql query report', function () {
    $schedule = makeDownloadableSchedule([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => 'SELECT id, email FROM users',
    ]);

    livewire(ViewExportSchedule::class, ['record' => $schedule->getKey()])
        ->callAction('download', ['format' => 'xlsx']);

    $export = Export::first();

    expect($export->completed_at)->not->toBeNull();
    expect($export->getFileDisk()->exists($export->getFileDirectory() . DIRECTORY_SEPARATOR . $export->file_name . '.xlsx'))->toBeTrue();
});

it('produces a downloadable file for a sql query report that matches no rows', function () {
    $schedule = makeDownloadableSchedule([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => "SELECT id, email FROM users WHERE email = 'nobody@nowhere.test'",
    ]);

    livewire(ViewExportSchedule::class, ['record' => $schedule->getKey()])
        ->callAction('download', ['format' => 'xlsx']);

    $export = Export::first();
    $disk = $export->getFileDisk();
    $directory = $export->getFileDirectory();

    expect($export->total_rows)->toBe(0);

    // Headers are still written, so the xlsx job has something to read and the file exists.
    expect($disk->exists($directory . DIRECTORY_SEPARATOR . 'headers.csv'))->toBeTrue();
    expect($disk->get($directory . DIRECTORY_SEPARATOR . 'headers.csv'))->toContain('email');
    expect($disk->exists($directory . DIRECTORY_SEPARATOR . $export->file_name . '.xlsx'))->toBeTrue();
});

it('hides the download action when a sql query report is not a safe select', function () {
    $schedule = makeDownloadableSchedule([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => 'DELETE FROM users',
    ]);

    livewire(ViewExportSchedule::class, ['record' => $schedule->getKey()])
        ->assertActionHidden('download');
});

it('offers the download action on the list table', function () {
    $schedule = makeDownloadableSchedule();

    livewire(ListExportSchedules::class)
        ->assertOk()
        ->assertTableActionExists('download')
        ->assertTableActionVisible('download', record: $schedule);
});

it('notifies only the requester and never the schedule cc list', function () {
    Notification::fake();

    $copied = User::create(['name' => 'Copied', 'email' => 'copied@domain.com', 'password' => 'password']);

    $schedule = makeDownloadableSchedule(['cc' => [$copied->id]]);

    $exporter = (new ScheduledExporter($schedule))->forUser(auth()->user());
    $exporter->run();

    (new ScheduledExportCompletion($exporter->getExport()->fresh(), $schedule, isAdHoc: true))->handle();

    Notification::assertSentTo(auth()->user(), ScheduledExportCompleteNotification::class);
    Notification::assertNotSentTo($copied, ScheduledExportCompleteNotification::class);
    expect(Export::count())->toBe(1);
});

it('still sends an empty ad hoc report when the schedule would suppress it', function () {
    Notification::fake();

    $schedule = makeDownloadableSchedule(['send_empty_report' => false]);

    $export = Export::create([
        'exporter' => UserExporter::class,
        'total_rows' => 0,
        'file_disk' => 'local',
        'file_name' => 'empty',
        'user_id' => auth()->id(),
        'user_type' => get_class(auth()->user()),
    ]);

    (new ScheduledExportCompletion($export, $schedule, isAdHoc: true))->handle();

    Notification::assertSentTo(auth()->user(), ScheduledExportCompleteNotification::class);
});

it('normalises formats stored as strings or enums', function () {
    $wantsXlsx = function (ExportSchedule $schedule): bool {
        $method = new ReflectionMethod(ScheduledExporter::class, 'wantsFormat');

        return $method->invoke(new ScheduledExporter($schedule), ExportFormat::Xlsx);
    };

    expect($wantsXlsx(makeDownloadableSchedule(['formats' => ['xlsx']])))->toBeTrue();
    expect($wantsXlsx(makeDownloadableSchedule(['formats' => [ExportFormat::Xlsx]])))->toBeTrue();
    expect($wantsXlsx(makeDownloadableSchedule(['formats' => ['csv']])))->toBeFalse();
    expect($wantsXlsx(makeDownloadableSchedule(['formats' => null])))->toBeFalse();
});
