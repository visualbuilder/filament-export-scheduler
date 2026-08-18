<?php

namespace Visualbuilder\ExportScheduler\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Implement this on an Exporter to make chosen columns clickable through to
 * the record they describe — an "Order ID" column linking to the order
 * itself, an "End User" column linking to the user a relation resolves to —
 * the same way they already are on that record's own Filament table.
 *
 * The report viewer only asks for links on the columns a report actually
 * selected, so there is nothing for whoever builds a report to configure:
 * every report built from this exporter gets whichever of its columns the
 * exporter's author chose to link, automatically.
 *
 * Only exporters with a genuinely linkable column need implement this —
 * an exporter over a model with no admin resource, or one whose columns are
 * all aggregates or computed values, has nothing to gain from it.
 */
interface HasLinkedColumns
{
    /**
     * Column name => resolver. Each resolver is called with the row's
     * exported model and returns the URL that column's cell should link to,
     * or null for no link on that row (e.g. no related record, or the
     * resolver chooses not to link an unauthorized record).
     *
     * @return array<string, callable(Model $record): (string|null)>
     */
    public static function getColumnLinks(): array;
}
