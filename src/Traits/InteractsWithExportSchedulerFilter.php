<?php

namespace VisualBuilder\ExportScheduler;

trait InteractsWithExportScheduleFilter
{
    protected string $filterOptionId;

    protected string $filterOptionLabel = 'name';

    public function setFilterOptionId(string $key = 'id'): void
    {
        $this->filterOptionId = $key;
    }

    public function setFilterOptionLabel(string $label = 'name'): void
    {
        $this->filterOptionLabel = $label;
    }

    public function getFilterId(): string
    {
        return $this->id;
    }

    public function getFilterLabel(): string
    {
        return $this->label;
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
