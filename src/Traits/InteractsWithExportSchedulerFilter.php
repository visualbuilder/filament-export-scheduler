<?php

namespace VisualBuilder\ExportScheduler\Traits;

trait InteractsWithExportSchedulerFilter
{
    protected $filterOptionLabel = 'name';

    public function getFilterLabel(): string
    {
        return $this->filterOptionLabel;
    }

    /*
     * Exclude relations that should
     * not be added to the filter
     * @return <array>Illuminate\Database\Eloquent\Relations\Relations
     */
    public function excludeFilterableRelations(): array
    {
        return [];
    }
}
