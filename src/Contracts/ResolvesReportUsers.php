<?php

namespace Visualbuilder\ExportScheduler\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Every id, label and email address the package reads off a user goes through
 * this. The package ships to unknown applications and cannot assume a user
 * model has an `email` column, a `name`, or an integer primary key.
 *
 * The default implementation is {@see \Visualbuilder\ExportScheduler\Support\ReportUserResolver},
 * bound as a singleton from `config('export-scheduler.user_resolver')`. Bind your
 * own implementation to replace it everywhere at once.
 */
interface ResolvesReportUsers
{
    /**
     * The user classes a report may be shared with or sent to.
     *
     * @return array<class-string<Model>, string> class-string => human label
     */
    public function userTypes(): array;

    /**
     * The value stored in a morph id column or in `visible_to_ids`.
     */
    public function getId(Model $user): int | string;

    /**
     * The text shown in every picker, table cell and email.
     */
    public function getLabel(Model $user): string;

    /**
     * Where an export email is delivered. Null means this user cannot be emailed.
     */
    public function getEmail(Model $user): ?string;

    /**
     * Options for a picker of users of the given class.
     *
     * @param  class-string<Model>  $model
     * @param  array<int|string>  $exclude  ids to leave out, e.g. the already-chosen recipient
     * @return array<int|string, string> id => label
     */
    public function options(string $model, ?string $search = null, array $exclude = []): array;

    /**
     * Labels for ids that are already selected, for Filament's getOptionLabelsUsing().
     *
     * @param  class-string<Model>  $model
     * @param  array<int|string>  $ids
     * @return array<int|string, string> id => label
     */
    public function labelsFor(string $model, array $ids): array;

    /**
     * Resolve a single user of the given class by id, or null when it no longer exists.
     *
     * @param  class-string<Model>  $model
     */
    public function find(string $model, int | string $id): ?Model;
}
