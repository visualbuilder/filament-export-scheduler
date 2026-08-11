<?php

namespace Visualbuilder\ExportScheduler\Filament\Forms;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Visualbuilder\ExportScheduler\Contracts\BypassesReportVisibility;
use Visualbuilder\ExportScheduler\Contracts\ResolvesReportUsers;
use Visualbuilder\ExportScheduler\Models\CustomReport;

/**
 * The delivery half of the form: when to run a report, and who receives it.
 *
 * Composed by both mounts — the schedules relation manager on a report's edit
 * page, and the standalone Report Schedules pages — so the modal and the full
 * page cannot drift apart.
 */
class ScheduleFields
{
    /**
     * @param  bool  $includeReportPicker  false when the report is already fixed,
     *                                     as it is inside the relation manager
     * @return array<\Filament\Schemas\Components\Component>
     */
    public static function schema(bool $includeReportPicker = true): array
    {
        // Every top-level component spans the full width. A resource form schema
        // is 2-column by default, so without this the whole form is squeezed into
        // the left half.
        return array_values(array_filter([
            $includeReportPicker ? static::reportPicker()->columnSpanFull() : null,

            // Timing on the left, stacked; recipient alongside it on the right.
            Grid::make()
                ->columnSpanFull()
                ->schema([
                    Group::make([
                        Section::make(__('export-scheduler::scheduler.when_to_run'))
                            ->columns()
                            ->schema([
                                Fields::scheduleFrequency(),
                                Fields::enableToggle(),
                                Fields::scheduleTime(),
                                Fields::scheduleDayOfWeek(),
                                Fields::scheduleDayOfMonth(),
                                Fields::scheduleMonth(),
                                Fields::scheduleStartMonth(),
                                Fields::customCronExpression(),
                                Fields::cronHint(),
                                Fields::scheduleTimeZone(),
                            ]),

                        Section::make(__('export-scheduler::scheduler.when_to_send'))
                            ->schema([Fields::sendEmptyReport()]),
                    ]),

                    Group::make([
                        Section::make(__('export-scheduler::scheduler.recipient'))
                            ->schema([
                                static::recipientType(),
                                static::recipientId(),
                                static::copyToUser(),
                            ]),

                        // Hidden by default. Existing rows keep fanning out at runtime; this
                        // only governs whether the fields can be reached in the UI
                        config('export-scheduler.dynamic_recipients', false)
                            ? Fields::automaticRecipients()->columnSpanFull()
                            : null,
                    ]),
                ]),

            // Full width on its own row. Null means inherit from the report, said
            // in the helper text so an empty field does not read as missing.
            Section::make(__('export-scheduler::scheduler.schedule_overrides'))
                ->description(__('export-scheduler::scheduler.schedule_overrides_description'))
                ->columns()
                ->columnSpanFull()
                ->collapsed()
                ->collapsible()
                ->schema([
                    Fields::dateRange()
                        ->required(false)
                        ->helperText(__('export-scheduler::scheduler.inherits_from_report')),
                    Fields::formats()
                        ->required(false)
                        ->default(null)
                        ->helperText(__('export-scheduler::scheduler.inherits_from_report')),
                ]),
        ]));
    }

    /**
     * Only reports the signed-in user owns — a report shared with you is readable,
     * but scheduling it is the owner's call. See {@see static::schedulableReports()}
     * for the one exception.
     *
     * Deliberately not ->relationship(): that would pair with the options below
     * rather than replace them, and the relationship's own scope is applied at a
     * different point in the lifecycle.
     */
    public static function reportPicker(): Select
    {
        return Select::make('custom_report_id')
            ->label(__('export-scheduler::scheduler.report'))
            ->options(fn () => static::schedulableReports()->pluck('name', 'id')->all())
            ->getSearchResultsUsing(fn (string $search) => static::schedulableReports()
                ->where('name', 'like', "%{$search}%")
                ->pluck('name', 'id')
                ->all())
            ->getOptionLabelUsing(fn ($value) => static::schedulableReports()->find($value)?->name)
            ->helperText(fn () => static::schedulableReports()->doesntExist()
                ? __('export-scheduler::scheduler.no_reports_yet')
                : null)
            ->searchable()
            ->preload()
            ->required()
            ->native(false);
    }

    /**
     * The reports this user may attach a schedule to: their own, plus every report
     * at all if they hold a visibility bypass. Without the bypass arm, an admin
     * editing somebody else's schedule would see the picker render blank — the
     * label lookup would miss the very report the schedule already points at.
     *
     * @return \Illuminate\Database\Eloquent\Builder<CustomReport>
     */
    protected static function schedulableReports(): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return CustomReport::query()->whereRaw('1 = 0');
        }

        if (app(BypassesReportVisibility::class)->can($user)) {
            return CustomReport::query();
        }

        return CustomReport::query()
            ->where('owner_type', $user::class)
            ->where('owner_id', $user->getKey());
    }

    /**
     * Split from a MorphToSelect so the type half can clear its dependants when
     * it changes — MorphToSelect does not expose its type sub-field for that.
     */
    public static function recipientType(): Select
    {
        return Select::make('recipient_type')
            ->label(__('export-scheduler::scheduler.recipient_type'))
            ->options(fn () => app(ResolvesReportUsers::class)->userTypes())
            ->native(false)
            ->required()
            ->live()
            ->afterStateUpdated(function (Set $set) {
                // Bare ids are meaningless once the class moves — Admin #7 is not
                // Associate #7. Mirrors the saving() guard on the model.
                $set('recipient_id', null);
                $set('cc', []);
            });
    }

    public static function recipientId(): Select
    {
        return Select::make('recipient_id')
            ->label(__('export-scheduler::scheduler.recipient'))
            ->options(function (Get $get) {
                $type = $get('recipient_type');

                return $type ? app(ResolvesReportUsers::class)->options($type) : [];
            })
            ->getSearchResultsUsing(function (string $search, Get $get) {
                $type = $get('recipient_type');

                return $type ? app(ResolvesReportUsers::class)->options($type, $search) : [];
            })
            ->getOptionLabelUsing(function ($value, Get $get) {
                $type = $get('recipient_type');

                return $type ? (app(ResolvesReportUsers::class)->labelsFor($type, [$value])[$value] ?? null) : null;
            })
            ->visible(fn (Get $get) => filled($get('recipient_type')))
            ->searchable()
            ->required()
            ->native(false)
            ->live();
    }

    public static function copyToUser(): Fieldset
    {
        return Fieldset::make(__('export-scheduler::scheduler.cc'))
            ->visible(fn (Get $get) => filled($get('recipient_id')))
            ->schema([
                TextEntry::make('cc_warning')
                    ->hiddenLabel()
                    ->belowContent(fn (Get $get) => new HtmlString(__(
                        'export-scheduler::scheduler.cc_warning',
                        ['owner_type' => class_basename((string) $get('recipient_type'))]
                    ))),

                Repeater::make('cc')
                    ->hiddenLabel()
                    ->addActionLabel(__('export-scheduler::scheduler.cc_add_label'))
                    ->simple(static::selectCopyToUser()),
            ]);
    }

    /**
     * A cc user is always the recipient's class — that invariant is what lets cc
     * stay a flat array of ids rather than {type, id} pairs.
     */
    public static function selectCopyToUser(): Select
    {
        return Select::make('id')
            ->label(__('export-scheduler::scheduler.recipient'))
            ->placeholder(__('export-scheduler::scheduler.cc_placeholder'))
            ->searchable()
            ->native(false)
            ->options(function (Get $get) {
                $type = $get('../../recipient_type');

                if (! $type) {
                    return [];
                }

                return app(ResolvesReportUsers::class)->options(
                    $type,
                    exclude: static::excludedCcIds($get),
                );
            })
            ->getSearchResultsUsing(function (string $search, Get $get) {
                $type = $get('../../recipient_type');

                if (! $type) {
                    return [];
                }

                return app(ResolvesReportUsers::class)->options(
                    $type,
                    $search,
                    static::excludedCcIds($get),
                );
            })
            ->getOptionLabelUsing(function ($value, Get $get) {
                $type = $get('../../recipient_type');

                return $type ? (app(ResolvesReportUsers::class)->labelsFor($type, [$value])[$value] ?? null) : null;
            });
    }

    /**
     * The recipient plus anyone already cc'd — nobody should be listed twice.
     *
     * @return array<int|string>
     */
    protected static function excludedCcIds(Get $get): array
    {
        $alreadyChosen = collect($get('../../cc') ?? [])
            ->map(fn ($entry) => is_array($entry) ? ($entry['id'] ?? null) : $entry)
            ->all();

        return array_values(array_filter(array_merge(
            $alreadyChosen,
            [$get('../../recipient_id')],
        )));
    }
}
