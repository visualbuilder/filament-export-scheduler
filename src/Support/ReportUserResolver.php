<?php

namespace Visualbuilder\ExportScheduler\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Visualbuilder\ExportScheduler\Contracts\HasExportReportIdentity;
use Visualbuilder\ExportScheduler\Contracts\ResolvesReportUsers;

/**
 * The default {@see ResolvesReportUsers}, driven by `config('export-scheduler.user_models')`.
 *
 * Per user, three steps, first hit wins:
 *
 *   1. {@see HasExportReportIdentity} on the model.
 *   2. `data_get()` against the configured attribute path, which may traverse
 *      relations — `contact.email` works with no code.
 *   3. A last-resort label of `Class #key` so pickers never render blank
 *      options, and a null address so mail is skipped rather than misdirected.
 *
 * `title_attribute` and `email_attribute` each default to `'email'`
 * independently. `email_attribute` deliberately does not inherit
 * `title_attribute`: a model labelled by `full_name` must not have mail
 * addressed to a person's name.
 */
class ReportUserResolver implements ResolvesReportUsers
{
    /**
     * How many options a picker loads before the user has typed a search term.
     */
    protected const OPTION_LIMIT = 50;

    public function userTypes(): array
    {
        return collect($this->userModels())
            ->mapWithKeys(fn (array $entry) => [
                $entry['model'] => $entry['model_label'] ?? class_basename($entry['model']),
            ])
            ->all();
    }

    public function getId(Model $user): int | string
    {
        return $user->getKey();
    }

    public function getLabel(Model $user): string
    {
        if ($user instanceof HasExportReportIdentity) {
            return $user->getExportReportLabel();
        }

        $label = data_get($user, $this->titleAttribute($user::class));

        return filled($label)
            ? (string) $label
            : class_basename($user) . ' #' . $user->getKey();
    }

    public function getEmail(Model $user): ?string
    {
        if ($user instanceof HasExportReportIdentity) {
            return $user->getExportReportEmail();
        }

        $email = data_get($user, $this->emailAttribute($user::class));

        return filled($email) ? (string) $email : null;
    }

    public function options(string $model, ?string $search = null, array $exclude = []): array
    {
        $query = $this->query($model);

        if (filled($exclude)) {
            $query->whereNotIn((new $model)->getKeyName(), $exclude);
        }

        if (filled($search)) {
            $this->applySearch($query, $model, $search);
        }

        return $query
            ->limit(static::OPTION_LIMIT)
            ->get()
            ->mapWithKeys(fn (Model $user) => [$this->getId($user) => $this->getLabel($user)])
            ->all();
    }

    public function labelsFor(string $model, array $ids): array
    {
        if (blank($ids)) {
            return [];
        }

        return $this->query($model)
            ->whereIn((new $model)->getKeyName(), $ids)
            ->get()
            ->mapWithKeys(fn (Model $user) => [$this->getId($user) => $this->getLabel($user)])
            ->all();
    }

    public function find(string $model, int | string $id): ?Model
    {
        if (! $this->isConfigured($model)) {
            return null;
        }

        return $this->query($model)->find($id);
    }

    /**
     * Is this class one the application has opted into? Guards against an id
     * from a stale list being resolved against an arbitrary class.
     *
     * @param  class-string<Model>  $model
     */
    public function isConfigured(string $model): bool
    {
        return array_key_exists($model, $this->userTypes());
    }

    /**
     * Base query for a user class, eager loading whichever relations the
     * configured attribute paths traverse so a picker is not N+1.
     *
     * @param  class-string<Model>  $model
     */
    protected function query(string $model): Builder
    {
        $query = $model::query();

        $relations = collect([$this->titleAttribute($model), $this->emailAttribute($model)])
            ->filter(fn (string $path) => str_contains($path, '.'))
            ->map(fn (string $path) => Str::beforeLast($path, '.'))
            ->unique()
            ->filter(fn (string $relation) => method_exists($model, Str::before($relation, '.')))
            ->all();

        return filled($relations) ? $query->with($relations) : $query;
    }

    /**
     * Search the label attribute, stepping into a relation when the configured
     * path is dotted — a `where` on `contact.email` would look for a column of
     * that name and fail.
     *
     * @param  class-string<Model>  $model
     */
    protected function applySearch(Builder $query, string $model, string $search): void
    {
        $attribute = $this->titleAttribute($model);

        if (! str_contains($attribute, '.')) {
            $query->where($attribute, 'like', "%{$search}%");

            return;
        }

        $relation = Str::beforeLast($attribute, '.');
        $column = Str::afterLast($attribute, '.');

        if (! method_exists($model, Str::before($relation, '.'))) {
            return;
        }

        $query->whereHas($relation, fn (Builder $q) => $q->where($column, 'like', "%{$search}%"));
    }

    /**
     * @param  class-string<Model>  $model
     */
    protected function titleAttribute(string $model): string
    {
        return $this->configFor($model)['title_attribute'] ?? 'email';
    }

    /**
     * @param  class-string<Model>  $model
     */
    protected function emailAttribute(string $model): string
    {
        return $this->configFor($model)['email_attribute'] ?? 'email';
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<string, string>
     */
    protected function configFor(string $model): array
    {
        foreach ($this->userModels() as $entry) {
            if ($entry['model'] === $model) {
                return $entry;
            }
        }

        return [];
    }

    /**
     * Normalised `user_models` config: every entry an array with at least a
     * `model` key. Entries given as a bare class string are accepted too.
     *
     * @return array<int, array<string, string>>
     */
    protected function userModels(): array
    {
        return collect(config('export-scheduler.user_models', []))
            ->map(fn ($entry) => is_array($entry) ? $entry : ['model' => $entry])
            ->filter(fn (array $entry) => filled($entry['model'] ?? null) && class_exists($entry['model']))
            ->values()
            ->all();
    }
}
