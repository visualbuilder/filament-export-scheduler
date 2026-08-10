<?php

namespace Visualbuilder\ExportScheduler\Models\Concerns;

use Carbon\Carbon;
use Visualbuilder\ExportScheduler\Enums\DateRange;

/**
 * Turns a {@see DateRange} into concrete start and end timestamps.
 *
 * Shared by CustomReport, which holds the default range, and ScheduledReport,
 * which may override it. Each supplies the range through effectiveDateRange();
 * everything below is the same for both.
 */
trait ResolvesDateRange
{
    /**
     * The range this record actually queries with.
     */
    abstract public function effectiveDateRange(): ?DateRange;

    public function getStartsAtAttribute(): ?Carbon
    {
        return $this->effectiveDateRange()?->getDateRange()['start'] ?? null;
    }

    public function getEndsAtAttribute(): ?Carbon
    {
        return $this->effectiveDateRange()?->getDateRange()['end'] ?? null;
    }

    public function getStartsAtFormattedAttribute(): string
    {
        return $this->starts_at ? $this->starts_at->format("l jS F Y \a\t h:i A") : '';
    }

    public function getEndsAtFormattedAttribute(): string
    {
        return $this->ends_at ? $this->ends_at->format("l jS F Y \a\t h:i A") : '';
    }

    public function getDateRangeLabelAttribute(): ?string
    {
        return $this->effectiveDateRange()?->getLabel();
    }
}
