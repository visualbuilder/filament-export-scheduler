<?php

namespace VisualBuilder\ExportScheduler\Enums;

trait EnumSubset
{
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function selectArray(): array
    {
        $array = [];
        foreach (self::cases() as $case) {
            $array[$case->value] = $case->getLabel();
        }

        return $array;
    }

    public function is(self | string | null $value): bool
    {
        if ($value === null) {
            return false;
        }

        if ($value instanceof self) {
            return $this === $value;
        }

        return $this->value === $value;
    }
}
