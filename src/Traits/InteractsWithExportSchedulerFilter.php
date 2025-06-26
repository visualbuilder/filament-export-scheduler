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
     * Exclude attributes that should
     * not be added to the filter
     * @return <array>string
     */
    public static function excludeFilterableAttributes(): array
    {
        return [];
    }

    /*
     * Exclude relations that should
     * not be added to the filter
     * @return <array>Illuminate\Database\Eloquent\Relations\Relations
     */
    public static function excludeFilterableRelations(): array
    {
        return [];
    }
}
