<?php

use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Jobs\CreateXlsxFile;
use Filament\Actions\Exports\Models\Export;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Visualbuilder\ExportScheduler\Enums\ReportType;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\ViewCustomReport;
use Visualbuilder\ExportScheduler\Jobs\CreateSqlQueryXlsxFile;
use Visualbuilder\ExportScheduler\Jobs\ScheduledExportCompletion;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;
use Visualbuilder\ExportScheduler\Notifications\ScheduledExportCompleteNotification;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;
use Visualbuilder\ExportScheduler\Tests\Models\User;

use function Pest\Livewire\livewire;

function makeDownloadableReport(array $overrides = []): CustomReport
{
    return CustomReport::create(array_merge([
        'name' => 'Downloadable Report',
        'report_type' => ReportType::EXPORTER,
        'exporter' => UserExporter::class,
        'columns' => [
            ['name' => 'id', 'label' => 'ID'],
            ['name' => 'email', 'label' => 'Email'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ], $overrides));
}

function makeDownloadableSchedule(CustomReport $report, array $overrides = []): ScheduledReport
{
    return ScheduledReport::create(array_merge([
        'custom_report_id' => $report->id,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '08:00',
        'schedule_timezone' => 'UTC',
        'enabled' => true,
    ], $overrides));
}

it('runs the export for the user who asked for it', function () {
    $report = makeDownloadableReport();

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->callAction('download', ['format' => ExportFormat::Csv->value])
        ->assertHasNoFormErrors();

    expect(Export::count())->toBe(1);

    // The download route only serves an export to the user it belongs to.
    expect(Export::first()->user_id)->toBe(auth()->id());
});

it('defaults the download format to xlsx, since a report carries no format of its own', function () {
    $report = makeDownloadableReport();

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->mountAction('download')
        ->assertSchemaStateSet(['format' => ExportFormat::Xlsx->value]);
});

it('creates the xlsx file when xlsx is chosen', function () {
    Bus::fake();

    $report = makeDownloadableReport();

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->callAction('download', ['format' => ExportFormat::Xlsx->value]);

    Bus::assertChained([
        Illuminate\Bus\ChainedBatch::class,
        CreateXlsxFile::class,
        ScheduledExportCompletion::class,
    ]);
});

it('does not create the xlsx file when csv is chosen', function () {
    Bus::fake();

    $report = makeDownloadableReport(['formats' => [ExportFormat::Xlsx->value]]);

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->callAction('download', ['format' => ExportFormat::Csv->value]);

    Bus::assertChained([
        Illuminate\Bus\ChainedBatch::class,
        ScheduledExportCompletion::class,
    ]);
});

it('builds the xlsx file for a sql query report without an exporter class', function () {
    Bus::fake();

    $report = makeDownloadableReport([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => 'SELECT id, email FROM users',
    ]);

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->callAction('download', ['format' => ExportFormat::Xlsx->value]);

    Bus::assertChained([
        Visualbuilder\ExportScheduler\Jobs\ExportSqlQuery::class,
        CreateSqlQueryXlsxFile::class,
        ScheduledExportCompletion::class,
    ]);
});

it('writes the xlsx file end to end for an exporter report', function () {
    $report = makeDownloadableReport();

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->callAction('download', ['format' => ExportFormat::Xlsx->value]);

    $export = Export::first();

    // A sync queue has run the whole chain, so the file is on disk and ready to download.
    expect($export->completed_at)->not->toBeNull();
    expect($export->getFileDisk()->exists($export->getFileDirectory() . DIRECTORY_SEPARATOR . $export->file_name . '.xlsx'))->toBeTrue();
});

it('writes the xlsx file end to end for a sql query report', function () {
    $report = makeDownloadableReport([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => 'SELECT id, email FROM users',
    ]);

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->callAction('download', ['format' => ExportFormat::Xlsx->value]);

    $export = Export::first();

    expect($export)
        ->completed_at->not->toBeNull()
        ->getFileDisk()->exists($export->getFileDirectory() . DIRECTORY_SEPARATOR . $export->file_name . '.xlsx')->toBeTrue();
});

it('produces a downloadable file for a sql query report that matches no rows', function () {
    $report = makeDownloadableReport([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => "SELECT id, email FROM users WHERE email = 'nobody@nowhere.test'",
    ]);

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->callAction('download', ['format' => ExportFormat::Xlsx->value]);

    $export = Export::first();
    $disk = $export->getFileDisk();
    $directory = $export->getFileDirectory();

    expect($export->total_rows)->toBe(0);

    // Headers are still written, so the xlsx job has something to read and the file exists.
    expect($disk)
        ->exists($directory . DIRECTORY_SEPARATOR . 'headers.csv')->toBeTrue()
        ->exists($directory . DIRECTORY_SEPARATOR . $export->file_name . '.xlsx')->toBeTrue()
        ->get($directory . DIRECTORY_SEPARATOR . 'headers.csv')->toContain('email');
});

it('hides the download action when a sql query report is not a safe select', function () {
    $report = makeDownloadableReport([
        'report_type' => ReportType::SQL_QUERY,
        'exporter' => null,
        'columns' => null,
        'sql_query' => 'DELETE FROM users',
    ]);

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->assertActionHidden('download');
});

it('notifies only the requester and never the schedule cc list', function () {
    Notification::fake();

    $requester = auth()->user();
    $cc1 = User::create(['name' => 'CC User 1', 'email' => 'cc1@domain.com', 'password' => 'password']);
    $cc2 = User::create(['name' => 'CC User 2', 'email' => 'cc2@domain.com', 'password' => 'password']);
    $unrelated = User::create(['name' => 'Unrelated', 'email' => 'unrelated@domain.com', 'password' => 'password']);

    $report = makeDownloadableReport();

    // Create a schedule with CC'd users to simulate a real scheduled export
    $schedule = makeDownloadableSchedule($report, [
        'recipient_id' => $requester->id,
        'recipient_type' => get_class($requester),
        'cc' => [(string) $cc1->id, (string) $cc2->id],
    ]);

    // Run ad-hoc (forUser) - the requester is downloading on demand, not a scheduled run
    $exporter = (new ScheduledExporter($report, $schedule))->forUser($requester);
    $exporter->run();

    // Handle completion as ad-hoc
    (new ScheduledExportCompletion($exporter->getExport()->fresh(), $report, $schedule, isAdHoc: true))->handle();

    // An ad hoc download is never emailed — it lands in the requester's bell.
    Notification::assertNotSentTo($requester, ScheduledExportCompleteNotification::class);
    Notification::assertSentTo($requester, DatabaseNotification::class);

    // Verify CC'd users are not notified at all in ad-hoc mode
    foreach ([$cc1, $cc2, $unrelated] as $user) {
        Notification::assertNotSentTo($user, ScheduledExportCompleteNotification::class);
        Notification::assertNotSentTo($user, DatabaseNotification::class);
    }

    // Ad-hoc downloads should only create one Export record, never duplicates for CC list
    expect(Export::count())->toBe(1);
    expect($exporter->getExport()->user_id)->toBe($requester->id);
});

it('still sends an empty ad hoc report when the schedule would suppress it', function () {
    Notification::fake();

    $report = makeDownloadableReport();

    $export = Export::create([
        'exporter' => UserExporter::class,
        'total_rows' => 0,
        'file_disk' => 'local',
        'file_name' => 'empty',
        'user_id' => auth()->id(),
        'user_type' => get_class(auth()->user()),
    ]);

    (new ScheduledExportCompletion($export, $report, isAdHoc: true))->handle();

    // Still notified, still not emailed.
    Notification::assertNotSentTo(auth()->user(), ScheduledExportCompleteNotification::class);
    Notification::assertSentTo(auth()->user(), DatabaseNotification::class);
});

it('offers a download link rather than an email when a download completes', function () {
    Notification::fake();

    $report = makeDownloadableReport();

    $export = Export::create([
        'exporter' => UserExporter::class,
        'total_rows' => 3,
        'file_disk' => 'local',
        'file_name' => 'ready',
        'user_id' => auth()->id(),
        'user_type' => get_class(auth()->user()),
    ]);

    (new ScheduledExportCompletion(
        $export,
        $report,
        isAdHoc: true,
        formats: [ExportFormat::Csv->value],
        authGuard: 'web',
    ))->handle();

    Notification::assertSentTo(
        auth()->user(),
        DatabaseNotification::class,
        function (DatabaseNotification $notification): bool {
            $actions = $notification->data['actions'] ?? [];

            expect($actions)->toHaveCount(1)
                ->and($actions[0]['name'])->toBe('download_csv')
                ->and($actions[0]['url'])->toContain('exports');

            return true;
        },
    );
});

it('normalises formats stored as strings or enums', function () {
    $report = makeDownloadableReport();

    $wantsXlsx = function (?array $formats) use ($report): bool {
        $schedule = makeDownloadableSchedule($report, ['formats' => $formats]);
        $method = new ReflectionMethod(ScheduledExporter::class, 'wantsFormat');

        return $method->invoke(new ScheduledExporter($report, $schedule), ExportFormat::Xlsx);
    };

    expect($wantsXlsx([ExportFormat::Xlsx->value]))->toBeTrue();
    expect($wantsXlsx([ExportFormat::Xlsx]))->toBeTrue();
    expect($wantsXlsx([ExportFormat::Csv->value]))->toBeFalse();

    // Nothing set anywhere falls back to xlsx rather than producing no file.
    expect($wantsXlsx(null))->toBeTrue();
});

it('copies export files to cc users so their download links work', function () {
    $ccUser = User::create(['name' => 'CC User', 'email' => 'cc@domain.com', 'password' => 'password']);

    $report = makeDownloadableReport();
    $schedule = makeDownloadableSchedule($report, [
        'cc' => [$ccUser->id],
        'recipient_type' => User::class,
        'recipient_id' => User::factory()->create()->id,
    ]);

    $exporter = new ScheduledExporter($report, $schedule);
    $exporter->run();

    $exports = Export::all();
    expect($exports)->toHaveCount(2);

    // Both exports should have their files on disk at their own directories
    foreach ($exports as $export) {
        $disk = $export->getFileDisk();
        $directory = $export->getFileDirectory();

        expect($disk->exists($directory))->toBeTrue(
            "Export #{$export->id} files should exist in {$directory}"
        );
        expect($disk->exists($directory . DIRECTORY_SEPARATOR . 'headers.csv'))->toBeTrue(
            "headers.csv should exist for export #{$export->id}"
        );
    }
});
