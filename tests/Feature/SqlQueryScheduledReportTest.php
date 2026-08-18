<?php

use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Notification;
use Visualbuilder\ExportScheduler\Enums\ReportType;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Jobs\ExportSqlQuery;
use Visualbuilder\ExportScheduler\Jobs\ScheduledExportCompletion;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Notifications\ScheduledExportCompleteNotification;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;
use Visualbuilder\ExportScheduler\Tests\Models\Document;
use Visualbuilder\ExportScheduler\Tests\Models\User;

it('can create a sql query report schedule', function () {
    $query = 'SELECT id, email FROM users';
    $report = CustomReport::create([
        'name' => 'SQL Test Report',
        'report_type' => ReportType::SQL_QUERY,
        'sql_query' => $query,
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    expect($report)
        ->isSqlQuery()->toBeTrue()
        ->report_type->toBe(ReportType::SQL_QUERY)
        ->sql_query->toBe($query);
});

it('defaults report type to exporter for existing records', function () {
    $report = CustomReport::create([
        'name' => 'Standard Report',
        'exporter' => UserExporter::class,
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    expect($report)
        ->isSqlQuery()->toBeFalse()
        ->report_type->toBe(ReportType::EXPORTER);
});

it('validates SELECT-only queries', function () {
    expect(CustomReport::validateSqlQuery('SELECT id, email FROM users'))->toBeEmpty();
});

it('rejects INSERT statements', function () {
    $errors = CustomReport::validateSqlQuery("INSERT INTO users (name) VALUES ('test')");

    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('must begin with SELECT');
});

it('rejects UPDATE statements', function () {
    $errors = CustomReport::validateSqlQuery("UPDATE users SET name = 'test'");

    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('must begin with SELECT');
});

it('rejects DELETE statements', function () {
    $errors = CustomReport::validateSqlQuery('DELETE FROM users');

    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('must begin with SELECT');
});

it('rejects DROP statements embedded in select', function () {
    $errors = CustomReport::validateSqlQuery('SELECT 1; DROP TABLE users');

    expect($errors)->not->toBeEmpty();
    expect(collect($errors)->contains(fn ($e) => str_contains($e, 'DROP')))->toBeTrue();
});

it('rejects queries with semicolons', function () {
    $errors = CustomReport::validateSqlQuery('SELECT 1; SELECT 2;');

    expect($errors)->not->toBeEmpty();
    expect(collect($errors)->contains(fn ($e) => str_contains($e, 'Semicolons')))->toBeTrue();
});

it('rejects a single trailing semicolon', function () {
    $errors = CustomReport::validateSqlQuery('SELECT id FROM users;');

    expect($errors)->not->toBeEmpty();
    expect(collect($errors)->contains(fn ($e) => str_contains($e, 'Semicolons')))->toBeTrue();
});

it('rejects TRUNCATE in query', function () {
    expect(CustomReport::validateSqlQuery('SELECT 1; TRUNCATE TABLE users'))->not->toBeEmpty();
});

it('allows complex SELECT queries with subqueries', function () {
    $sql = 'SELECT u.id, u.email, (SELECT COUNT(*) FROM exports WHERE exports.user_id = u.id) as export_count FROM users u WHERE u.created_at > "2024-01-01"';

    expect(CustomReport::validateSqlQuery($sql))->toBeEmpty();
});

it('allows SELECT with JOIN', function () {
    $sql = 'SELECT users.id, users.email, organisations.name FROM users LEFT JOIN organisations ON users.id = organisations.primary_contact_id';

    expect(CustomReport::validateSqlQuery($sql))->toBeEmpty();
});

it('allows SELECT with GROUP BY and HAVING', function () {
    $sql = 'SELECT owner_type, COUNT(*) as total FROM documents GROUP BY owner_type HAVING total > 5';

    expect(CustomReport::validateSqlQuery($sql))->toBeEmpty();
});

it('rejects INTO OUTFILE', function () {
    $errors = CustomReport::validateSqlQuery("SELECT * FROM users INTO OUTFILE '/tmp/data.csv'");

    expect($errors)->not->toBeEmpty();
});

it('can run sql query export', function () {
    // The setUp already creates 1 user (admin@domain.com)
    // Create additional users for the SQL query to return
    User::create(['name' => 'User A', 'email' => 'a@test.com', 'password' => 'password']);
    User::create(['name' => 'User B', 'email' => 'b@test.com', 'password' => 'password']);

    $report = CustomReport::create([
        'name' => 'SQL User Report',
        'report_type' => ReportType::SQL_QUERY,
        'sql_query' => 'SELECT id, name, email FROM users',
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    $exporter = new ScheduledExporter($report);
    $result = $exporter->run();

    expect($result)->toBeTrue();
    expect($exporter->getTotalRows())->toBe(3); // admin + 2 created
});

it('rejects sql query export when validation fails', function () {
    $report = CustomReport::create([
        'name' => 'Bad SQL Report',
        'report_type' => ReportType::SQL_QUERY,
        'sql_query' => 'DELETE FROM users',
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    expect((new ScheduledExporter($report))->run())->toBeFalse();
});

it('correctly counts rows for complex query with GROUP BY', function () {
    // Create test data
    $user1 = User::create(['name' => 'User 1', 'email' => 'user1@test.com', 'password' => 'password']);
    $user2 = User::create(['name' => 'User 2', 'email' => 'user2@test.com', 'password' => 'password']);

    // Create documents for each user
    Document::create([
        'title' => 'Doc 1',
        'content' => 'Test',
        'owner_id' => $user1->id,
        'owner_type' => get_class($user1),
    ]);

    Document::create([
        'title' => 'Doc 2',
        'content' => 'Test',
        'owner_id' => $user2->id,
        'owner_type' => get_class($user2),
    ]);

    // Create report with GROUP BY query
    $report = CustomReport::create([
        'name' => 'Grouped SQL Report',
        'report_type' => ReportType::SQL_QUERY,
        'sql_query' => 'SELECT owner_type, COUNT(*) as total FROM documents GROUP BY owner_type',
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    $exporter = new ScheduledExporter($report);

    expect($exporter->run())->toBeTrue();

    // Should return 1 row (one owner_type group), not 0 or 2
    expect($exporter->getTotalRows())->toBe(1);
});

it('creates headers.csv file for SQL query export', function () {
    User::create(['name' => 'Test User', 'email' => 'test@test.com', 'password' => 'password']);

    $report = CustomReport::create([
        'name' => 'SQL Headers Test',
        'report_type' => ReportType::SQL_QUERY,
        'sql_query' => 'SELECT id, name, email FROM users',
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    (new ScheduledExporter($report))->run();

    // Process the export job synchronously
    $export = Export::latest()->first();
    $job = new ExportSqlQuery($export, $report->sql_query);
    $job->handle();

    // Verify headers.csv exists
    $disk = $export->getFileDisk();
    $headersPath = $export->getFileDirectory() . DIRECTORY_SEPARATOR . 'headers.csv';

    expect($disk->exists($headersPath))->toBeTrue();

    // Verify headers content
    expect($disk->get($headersPath))->toContain('id', 'name', 'email');
});

it('sends notification with download link after SQL export completes', function () {
    Notification::fake();

    User::create(['name' => 'Test User', 'email' => 'test@test.com', 'password' => 'password']);

    $report = CustomReport::create([
        'name' => 'SQL Notification Test',
        'report_type' => ReportType::SQL_QUERY,
        'sql_query' => 'SELECT id, name, email FROM users',
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    (new ScheduledExporter($report))->run();

    // Process the export job synchronously
    $export = Export::latest()->first();

    (new ExportSqlQuery($export, $report->sql_query))->handle();

    // Process the completion job
    (new ScheduledExportCompletion($export->fresh(), $report))->handle();

    // Verify notification was sent
    Notification::assertSentTo(
        auth()->user(),
        ScheduledExportCompleteNotification::class,
        function ($notification) use ($export) {
            return $notification->export->id === $export->id;
        }
    );
});

it('handles basic SELECT query correctly', function () {
    // Create multiple users
    User::create(['name' => 'Alice', 'email' => 'alice@test.com', 'password' => 'password']);
    User::create(['name' => 'Bob', 'email' => 'bob@test.com', 'password' => 'password']);
    User::create(['name' => 'Charlie', 'email' => 'charlie@test.com', 'password' => 'password']);

    $report = CustomReport::create([
        'name' => 'Basic SELECT Test',
        'report_type' => ReportType::SQL_QUERY,
        'sql_query' => "SELECT * FROM users WHERE created_at >= '" . now()->subDay()->toDateString() . "'",
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    $exporter = new ScheduledExporter($report);

    expect($exporter->run())->toBeTrue();
    // Should count all users created (including the auth user)
    expect($exporter->getTotalRows())->toBeGreaterThanOrEqual(3);
});
