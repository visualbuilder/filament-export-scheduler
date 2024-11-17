<?php

namespace VisualBuilder\ExportScheduler\Enums;

use Filament\Support\Contracts\HasLabel;

enum ScheduleFrequency: string implements HasLabel
{

    use EnumSubset;

    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case HALF_YEARLY = 'half-yearly';
    case YEARLY = 'yearly';
    case CRON = 'cron';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::DAILY       => __('export-scheduler::schedule_frequency.daily'),
            self::WEEKLY      => __('export-scheduler::schedule_frequency.weekly'),
            self::MONTHLY     => __('export-scheduler::schedule_frequency.monthly'),
            self::QUARTERLY   => __('export-scheduler::schedule_frequency.quarterly'),
            self::HALF_YEARLY => __('export-scheduler::schedule_frequency.half_yearly'),
            self::YEARLY      => __('export-scheduler::schedule_frequency.yearly'),
            self::CRON        => __('export-scheduler::schedule_frequency.cron'),
        };
    }

    public function is(string $value): bool
    {
        return $this->value === $value;
    }

}
