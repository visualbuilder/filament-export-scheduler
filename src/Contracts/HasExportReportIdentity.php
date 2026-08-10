<?php

namespace Visualbuilder\ExportScheduler\Contracts;

/**
 * Implement this on a user model when its display name or delivery address
 * cannot be expressed as an attribute path.
 *
 * The config keys `title_attribute` and `email_attribute` are read with
 * `data_get()`, so a relation such as `contact.email` already works without any
 * code. Reach for this contract only when the value is genuinely computed — a
 * preference lookup, a fallback chain, a formatted composite.
 *
 * Takes precedence over both config keys.
 */
interface HasExportReportIdentity
{
    /**
     * The text shown wherever this user appears: pickers, table cells, emails.
     */
    public function getExportReportLabel(): string;

    /**
     * Where an export email should be delivered.
     *
     * Return null when this user cannot receive mail. The mail channel is then
     * skipped with a logged warning rather than throwing, and any database
     * notification still lands.
     */
    public function getExportReportEmail(): ?string;
}
