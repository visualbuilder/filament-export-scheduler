<?php

namespace VisualBuilder\ExportScheduler\Support;

use Filament\Forms\Components\MorphToSelect;

class MorphToSelectHelper
{
    /**
     * Create a MorphToSelect field based on the configuration.
     *
     * @param string $label
     * @param string $fieldName
     * @param bool $native
     * @param bool $searchable
     * @return MorphToSelect
     */
    public static function createMorphToSelect(string $label, string $fieldName = 'owner', bool $native = false, bool $searchable = true): MorphToSelect
    {
        $userModels = config('export-scheduler.user_models', []);

        $types = [];
        foreach ($userModels as $userModel) {
            if (is_array($userModel) && isset($userModel['model'], $userModel['title_attribute'])) {
                $types[] = MorphToSelect\Type::make($userModel['model'])
                    ->titleAttribute($userModel['title_attribute']);
            }
        }

        return MorphToSelect::make($fieldName)
            ->label($label)
            ->types($types)
            ->native($native)
            ->searchable($searchable);
    }
}
