<?php

namespace VisualBuilder\ExportScheduler\Support;

use Filament\Actions\Exports\ExportColumn;

class ColumnHelper
{
    /**
     * Get default columns from an Exporter class.
     *
     * @param string $exporterClass
     * @return array
     */
    public static function getDefaultColumns(string $exporterClass): array
    {
        if (!class_exists($exporterClass)) {
            throw new \InvalidArgumentException("Exporter class {$exporterClass} does not exist.");
        }

        if (!method_exists($exporterClass, 'getColumns')) {
            throw new \InvalidArgumentException("Exporter class {$exporterClass} does not define a getColumns method.");
        }

        $columns = $exporterClass::getColumns();

        return collect($columns)
            ->filter(fn($column) => $column instanceof ExportColumn) // Ensure only ExportColumn instances
            ->map(fn(ExportColumn $column) => [
                'name' => $column->getName(),
                'label' => $column->getLabel() ?? $column->getName(),
            ])
            ->all();
    }
}
