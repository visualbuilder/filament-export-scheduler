<?php

use Visualbuilder\ExportScheduler\Enums\ReportType;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Filament\Exporters\UserExporter;
use Visualbuilder\ExportScheduler\Models\ExportSchedule;
use Visualbuilder\ExportScheduler\Services\ScheduledExporter;
use Visualbuilder\ExportScheduler\Tests\Models\User;

it('can create a sql query report schedule', function () {
    $schedule = ExportSchedule::create([
        'name' => 'SQL Test Report',
        'report_type' => ReportType::SQL_QUERY,
        'sql_query' => 'SELECT id, email FROM users',
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '08:00',
        'schedule_timezone' => 'UTC',
        'formats' => ['csv'],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'enabled' => true,
    ]);

    expect($schedule->report_type)->toBe(ReportType::SQL_QUERY);
    expect($schedule->sql_query)->toBe('SELECT id, email FROM users');
    expect($schedule->isSqlQuery())->toBeTrue();
});

it('defaults report type to exporter for existing records', function () {
    $schedule = ExportSchedule::create([
        'name' => 'Standard Report',
        'exporter' => UserExporter::class,
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '08:00',
        'columns' => [['name' => 'id', 'label' => 'ID']],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ]);

    expect($schedule->report_type)->toBe(ReportType::EXPORTER);
    expect($schedule->isSqlQuery())->toBeFalse();
});

it('validates SELECT-only queries', function () {
    $errors = ExportSchedule::validateSqlQuery('SELECT id, email FROM users');
    expect($errors)->toBeEmpty();
});

it('rejects INSERT statements', function () {
    $errors = ExportSchedule::validateSqlQuery("INSERT INTO users (name) VALUES ('test')");
    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('must begin with SELECT');
});

it('rejects UPDATE statements', function () {
    $errors = ExportSchedule::validateSqlQuery("UPDATE users SET name = 'test'");
    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('must begin with SELECT');
});

it('rejects DELETE statements', function () {
    $errors = ExportSchedule::validateSqlQuery('DELETE FROM users');
    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('must begin with SELECT');
});

it('rejects DROP statements embedded in select', function () {
    $errors = ExportSchedule::validateSqlQuery('SELECT 1; DROP TABLE users');
    expect($errors)->not->toBeEmpty();
    expect(collect($errors)->contains(fn ($e) => str_contains($e, 'DROP')))->toBeTrue();
});

it('rejects queries with semicolons', function () {
    $errors = ExportSchedule::validateSqlQuery('SELECT 1; SELECT 2;');
    expect($errors)->not->toBeEmpty();
    expect(collect($errors)->contains(fn ($e) => str_contains($e, 'Semicolons')))->toBeTrue();
});

it('rejects a single trailing semicolon', function () {
    $errors = ExportSchedule::validateSqlQuery('SELECT id FROM users;');
    expect($errors)->not->toBeEmpty();
    expect(collect($errors)->contains(fn ($e) => str_contains($e, 'Semicolons')))->toBeTrue();
});

it('rejects TRUNCATE in query', function () {
    $errors = ExportSchedule::validateSqlQuery('SELECT 1; TRUNCATE TABLE users');
    expect($errors)->not->toBeEmpty();
});

it('allows complex SELECT queries with subqueries', function () {
    $sql = 'SELECT u.id, u.email, (SELECT COUNT(*) FROM exports WHERE exports.user_id = u.id) as export_count FROM users u WHERE u.created_at > "2024-01-01"';
    $errors = ExportSchedule::validateSqlQuery($sql);
    expect($errors)->toBeEmpty();
});

it('allows SELECT with JOIN', function () {
    $sql = 'SELECT users.id, users.email, organisations.name FROM users LEFT JOIN organisations ON users.id = organisations.primary_contact_id';
    $errors = ExportSchedule::validateSqlQuery($sql);
    expect($errors)->toBeEmpty();
});

it('allows SELECT with GROUP BY and HAVING', function () {
    $sql = 'SELECT owner_type, COUNT(*) as total FROM documents GROUP BY owner_type HAVING total > 5';
    $errors = ExportSchedule::validateSqlQuery($sql);
    expect($errors)->toBeEmpty();
});

it('rejects INTO OUTFILE', function () {
    $errors = ExportSchedule::validateSqlQuery("SELECT * FROM users INTO OUTFILE '/tmp/data.csv'");
    expect($errors)->not->toBeEmpty();
});

it('can run sql query export', function () {
    // The setUp already creates 1 user (admin@domain.com)
    // Create additional users for the SQL query to return
    User::create(['name' => 'User A', 'email' => 'a@test.com', 'password' => 'password']);
    User::create(['name' => 'User B', 'email' => 'b@test.com', 'password' => 'password']);

    $schedule = ExportSchedule::create([
        'name' => 'SQL User Report',
        'report_type' => ReportType::SQL_QUERY,
        'sql_query' => 'SELECT id, name, email FROM users',
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '08:00',
        'schedule_timezone' => 'UTC',
        'formats' => ['csv'],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'enabled' => true,
    ]);

    $exporter = new ScheduledExporter($schedule);
    $result = $exporter->run();

    expect($result)->toBeTrue();
    expect($exporter->getTotalRows())->toBe(3); // admin + 2 created
});

it('rejects sql query export when validation fails', function () {
    $schedule = ExportSchedule::create([
        'name' => 'Bad SQL Report',
        'report_type' => ReportType::SQL_QUERY,
        'sql_query' => "DELETE FROM users",
        'schedule_frequency' => ScheduleFrequency::DAILY,
        'schedule_time' => '08:00',
        'schedule_timezone' => 'UTC',
        'formats' => ['csv'],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
        'enabled' => true,
    ]);

    $exporter = new ScheduledExporter($schedule);
    $result = $exporter->run();

    expect($result)->toBeFalse();
});
