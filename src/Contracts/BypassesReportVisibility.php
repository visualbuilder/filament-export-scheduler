<?php

namespace Visualbuilder\ExportScheduler\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Determines whether a user should see all reports and schedules, bypassing
 * visibility restrictions. By default, no user can bypass.
 *
 * The default implementation is {@see \Visualbuilder\ExportScheduler\Support\VisibilityBypass},
 * bound as a singleton from `config('export-scheduler.visibility_bypass')`. Bind your
 * own implementation to replace it everywhere at once.
 *
 * Example: Allow users with specific roles to see everything
 *
 *     class MyVisibilityBypass implements BypassesReportVisibility {
 *         public function can(?Model $user): bool {
 *             return $user && method_exists($user, 'hasRole')
 *                 && $user->hasRole(['admin', 'developer']);
 *         }
 *     }
 *
 *     // In your service provider:
 *     $this->app->bind(BypassesReportVisibility::class, MyVisibilityBypass::class);
 */
interface BypassesReportVisibility
{
    /**
     * Whether a user should bypass visibility restrictions and see all reports.
     */
    public function can(?Model $user): bool;
}
