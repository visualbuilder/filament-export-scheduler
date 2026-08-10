<?php

namespace Visualbuilder\ExportScheduler\Filament\Forms;

use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Visualbuilder\ExportScheduler\Contracts\ResolvesReportUsers;
use Visualbuilder\ExportScheduler\Enums\ReportVisibility;
use Visualbuilder\ExportScheduler\Models\CustomReport;

/**
 * The report-definition half of the form: what to export, and who may see it.
 *
 * Field builders that did not change in the split are still {@see Fields}
 * methods; this class composes them rather than copying them, so there is one
 * definition of each field.
 */
class ReportFields
{
    /**
     * Two tabs, Exporter and Columns. A report no longer has a schedule to
     * configure, so the old third tab is gone — see {@see ScheduleFields}.
     *
     * @return array<\Filament\Schemas\Components\Component>
     */
    public static function schema(): array
    {
        return [
            Tabs::make('tabs')
                ->tabs([
                    Tabs\Tab::make('Exporter')
                        ->label(fn (Get $get) => ($get('report_type') ?? 'exporter') === 'sql_query' ? 'SQL Query' : 'Exporter')
                        ->schema([
                            Section::make()
                                ->columnSpanFull()
                                ->schema([
                                    Grid::make()->schema([
                                        Fields::name(),
                                        Fields::reportType(),
                                    ]),
                                    Fields::exporter(),
                                    Fields::sqlQuery(),
                                ]),

                            // Report defaults. A schedule may override either.
                            Section::make(__('export-scheduler::scheduler.report_defaults'))
                                ->description(__('export-scheduler::scheduler.report_defaults_description'))
                                ->columns()
                                ->schema([
                                    Fields::dateRange(),
                                    Fields::formats(),
                                ]),

                            static::sharingSection(),

                            Fields::filterByAttributeSection(),
                            Fields::filterReportSection(),
                        ]),

                    Tabs\Tab::make('Columns')
                        ->schema([
                            Fields::availableColumns(),
                            Fields::columnsRepeater(),
                        ])
                        ->columns(4)
                        ->visible(fn (Get $get) => ($get('report_type') ?? 'exporter') !== 'sql_query'
                            && $get('exporter')
                            && CustomReport::getDefaultColumnsForExporter($get('exporter'))->count())
                        ->extraAttributes(['class' => 'column_picker']),
                ])
                ->contained(false)
                ->persistTab()
                ->persistTabInQueryString()
                ->columnSpanFull(),
        ];
    }

    /**
     * Who, besides the owner, may view and download this report.
     *
     * Both parents clear their dependent field live, so a stale id list is never
     * even briefly on screen. The model's saving() hook is the actual guarantee;
     * this is the visible half of the same rule.
     */
    public static function sharingSection(): Section
    {
        return Section::make(__('export-scheduler::scheduler.sharing'))
            ->description(__('export-scheduler::scheduler.sharing_description'))
            ->columns()
            ->schema([
                static::visibility(),
                static::visibleToType(),
                static::visibleToIds(),
            ]);
    }

    public static function visibility(): Radio
    {
        return Radio::make('visibility')
            ->label(__('export-scheduler::scheduler.visibility'))
            ->options(ReportVisibility::class)
            ->default(ReportVisibility::OWNER->value)
            ->required()
            ->inline()
            ->columnSpanFull()
            ->live()
            ->afterStateUpdated(function (Set $set, $state) {
                if ($state === ReportVisibility::OWNER->value) {
                    $set('visible_to_type', null);
                }

                $set('visible_to_ids', []);
            });
    }

    public static function visibleToType(): Select
    {
        return Select::make('visible_to_type')
            ->label(__('export-scheduler::scheduler.visible_to_type'))
            ->options(fn () => app(ResolvesReportUsers::class)->userTypes())
            ->native(false)
            ->required(fn (Get $get) => static::modeNeedsType($get('visibility')))
            ->visible(fn (Get $get) => static::modeNeedsType($get('visibility')))
            ->live()
            ->afterStateUpdated(fn (Set $set) => $set('visible_to_ids', []));
    }

    public static function visibleToIds(): Select
    {
        return Select::make('visible_to_ids')
            ->label(__('export-scheduler::scheduler.visible_to_ids'))
            ->placeholder(__('export-scheduler::scheduler.visible_to_ids_placeholder'))
            ->multiple()
            ->searchable()
            ->native(false)
            ->required(fn (Get $get) => static::modeNeedsIds($get('visibility')))
            ->visible(fn (Get $get) => static::modeNeedsIds($get('visibility')))
            ->options(function (Get $get) {
                $type = $get('visible_to_type');

                return $type ? app(ResolvesReportUsers::class)->options($type) : [];
            })
            ->getSearchResultsUsing(function (string $search, Get $get) {
                $type = $get('visible_to_type');

                return $type ? app(ResolvesReportUsers::class)->options($type, $search) : [];
            })
            ->getOptionLabelsUsing(function (array $values, Get $get) {
                $type = $get('visible_to_type');

                return $type ? app(ResolvesReportUsers::class)->labelsFor($type, $values) : [];
            });
    }

    protected static function modeNeedsType(mixed $visibility): bool
    {
        return static::mode($visibility)?->needsType() ?? false;
    }

    protected static function modeNeedsIds(mixed $visibility): bool
    {
        return static::mode($visibility)?->needsIds() ?? false;
    }

    /**
     * Form state arrives as a plain string while the user is editing, but as a
     * hydrated enum when the form is filled from a saved report, because the
     * model casts the column. Both have to resolve.
     */
    protected static function mode(mixed $visibility): ?ReportVisibility
    {
        return match (true) {
            $visibility instanceof ReportVisibility => $visibility,
            is_string($visibility) => ReportVisibility::tryFrom($visibility),
            default => null,
        };
    }
}
