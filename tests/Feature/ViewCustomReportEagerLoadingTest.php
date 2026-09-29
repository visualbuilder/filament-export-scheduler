<?php

use Illuminate\Support\Facades\DB;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages\ViewCustomReport;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Tests\Exporters\DocumentOwnerExporter;
use Visualbuilder\ExportScheduler\Tests\Models\Document;
use Visualbuilder\ExportScheduler\Tests\Models\User;

use function Pest\Livewire\livewire;

/**
 * @param  array<string, mixed>  $overrides
 */
function makeOwnerReport(array $overrides = []): CustomReport
{
    return CustomReport::create(array_merge([
        'name' => 'Owner Report',
        'report_type' => 'exporter',
        'exporter' => DocumentOwnerExporter::class,
        'columns' => [
            ['name' => 'title', 'label' => 'Title'],
            ['name' => 'owner.created_at', 'label' => 'Owner Created At'],
        ],
        'owner_id' => auth()->id(),
        'owner_type' => get_class(auth()->user()),
    ], $overrides));
}

it('eager loads a dot-notation relationship column in one query whatever the row count', function () {
    // Two rows, each with a distinct owner: the eager load for a MorphTo with a single
    // morph type is one extra query, not one per row.
    foreach ([1, 2] as $i) {
        $owner = User::create([
            'name' => "Owner {$i}",
            'email' => "owner{$i}@domain.com",
            'password' => 'password',
            'created_at' => now()->setDate(2023, 5, $i),
        ]);

        Document::create(['title' => "Doc {$i}", 'owner_id' => $owner->id, 'owner_type' => User::class]);
    }

    $report = makeOwnerReport();

    DB::flushQueryLog();
    DB::enableQueryLog();

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->assertOk()
        ->assertSee('2023-05-01')
        ->assertSee('2023-05-02');

    $smallQueryCount = count(DB::getQueryLog());

    Document::query()->delete();
    User::query()->where('email', 'like', 'owner%@domain.com')->delete();

    foreach (range(1, 6) as $i) {
        $owner = User::create([
            'name' => "Owner {$i}",
            'email' => "owner{$i}@domain.com",
            'password' => 'password',
            'created_at' => now()->setDate(2023, 6, $i),
        ]);

        Document::create(['title' => "Doc {$i}", 'owner_id' => $owner->id, 'owner_type' => User::class]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->assertOk()
        ->assertSee('2023-06-01')
        ->assertSee('2023-06-06');

    $largeQueryCount = count(DB::getQueryLog());

    expect($largeQueryCount)->toBe($smallQueryCount);
});

it('still caps the rows loaded when a viewer limit is configured', function () {
    config()->set('export-scheduler.viewer_max_rows', 3);

    foreach (range(1, 5) as $i) {
        $owner = User::create([
            'name' => "Owner {$i}",
            'email' => "owner{$i}@domain.com",
            'password' => 'password',
        ]);

        Document::create(['title' => "Doc {$i}", 'owner_id' => $owner->id, 'owner_type' => User::class]);
    }

    $report = makeOwnerReport();

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->assertOk()
        ->assertSee('Showing the first 3 rows')
        ->assertDontSee('Doc 4')
        ->assertDontSee('Doc 5');
});

it('eager loads a dot-notation relationship column in one query on the capped get() path too', function () {
    // viewer_max_rows set → getExporterRows() takes the get() branch, not lazy().
    // The eager load is applied to the query before either branch runs, but nothing
    // upstream of this test exercises get() with a relationship column and checks the
    // query count — assert it independently so a regression that reintroduces
    // per-row queries on the capped path is caught even though lazy() stays fixed.
    config()->set('export-scheduler.viewer_max_rows', 50);

    foreach ([1, 2] as $i) {
        $owner = User::create([
            'name' => "Owner {$i}",
            'email' => "owner{$i}@domain.com",
            'password' => 'password',
            'created_at' => now()->setDate(2023, 5, $i),
        ]);

        Document::create(['title' => "Doc {$i}", 'owner_id' => $owner->id, 'owner_type' => User::class]);
    }

    $report = makeOwnerReport();

    DB::flushQueryLog();
    DB::enableQueryLog();

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->assertOk()
        ->assertSee('2023-05-01')
        ->assertSee('2023-05-02');

    $smallQueryCount = count(DB::getQueryLog());

    Document::query()->delete();
    User::query()->where('email', 'like', 'owner%@domain.com')->delete();

    foreach (range(1, 6) as $i) {
        $owner = User::create([
            'name' => "Owner {$i}",
            'email' => "owner{$i}@domain.com",
            'password' => 'password',
            'created_at' => now()->setDate(2023, 6, $i),
        ]);

        Document::create(['title' => "Doc {$i}", 'owner_id' => $owner->id, 'owner_type' => User::class]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->assertOk()
        ->assertSee('2023-06-01')
        ->assertSee('2023-06-06');

    $largeQueryCount = count(DB::getQueryLog());

    expect($largeQueryCount)->toBe($smallQueryCount);
});

it('renders every row when the viewer limit is not configured', function () {
    config()->set('export-scheduler.viewer_max_rows', null);

    foreach (range(1, 6) as $i) {
        $owner = User::create([
            'name' => "Owner {$i}",
            'email' => "owner{$i}@domain.com",
            'password' => 'password',
        ]);

        Document::create(['title' => "Doc {$i}", 'owner_id' => $owner->id, 'owner_type' => User::class]);
    }

    $report = makeOwnerReport();

    $component = livewire(ViewCustomReport::class, ['record' => $report->getKey()])
        ->assertOk();

    foreach (range(1, 6) as $i) {
        $component->assertSee("Doc {$i}");
    }
});
