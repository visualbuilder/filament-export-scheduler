<?php


namespace VisualBuilder\ExportScheduler\Tests\Traits;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait CustomRefreshDatabase
{

    use RefreshDatabase {
        migrateFreshUsing as private originalMigrateFreshUsing;
    }

    protected function migrateFreshUsing()
    {
        return array_merge(
            [
                '--schema-path' => 'tests/database/schema/test-schema.sql'
            ],
            $this->originalMigrateFreshUsing()
        );
    }
}
