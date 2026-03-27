<?php

namespace Visualbuilder\ExportScheduler\Tests\Traits;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait CustomRefreshDatabase
{
    use RefreshDatabase {
        migrateFreshUsing as private originalMigrateFreshUsing;
    }

    protected function migrateFreshUsing()
    {
        // Temporarily disable schema-path loading due to mysql CLI unavailability
        // Use normal migrations instead
        return $this->originalMigrateFreshUsing();
    }
}
