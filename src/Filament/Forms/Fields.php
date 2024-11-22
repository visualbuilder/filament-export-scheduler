<?php

namespace VisualBuilder\ExportScheduler\Filament\Forms;

use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use VisualBuilder\ExportScheduler\Enums\DateRange;
use VisualBuilder\ExportScheduler\Enums\DayOfWeek;
use VisualBuilder\ExportScheduler\Enums\Month;
use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;
use VisualBuilder\ExportScheduler\Facades\ExportScheduler;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;

class Fields
{
    /**
     * @return TextInput
     */
    public static function name(): TextInput
    {
        return TextInput::make('name')
            ->label(__('export-scheduler::scheduler.name'))
            ->placeholder(__('export-scheduler::scheduler.name_placeholder'))
            ->required()
            ->maxLength(191);
    }

    /**
     * @return Select
     */
    public static function exporter(): Select
    {
        return Select::make('exporter')
            ->label(__('export-scheduler::scheduler.exporter'))
            ->hintColor('info')->hintIcon('heroicon-m-question-mark-circle', tooltip: __('export-scheduler::scheduler.exporter_hint'))
            ->hintColor('info')
            ->options(ExportScheduler::listExporters())
            ->native(false)
            ->reactive()
            ->required()
            ->afterStateUpdated(function (?ExportSchedule $record, $state, Set $set, $livewire) {
                /** Update the column definitions when changing exporter */
                $defaultColumns = ExportSchedule::getDefaultColumnsForExporter($state);

                $set('columns', $defaultColumns->toArray() ?? []);
                $set('available_columns', []);

                if ($record) {
                    $record->update([
                        'exporter' => $state,
                        'columns'  => $defaultColumns ?? [],
                    ]);
                }

            });
    }

    /**
     * @return Select
     */
    public static function scheduleFrequency(): Select
    {
        return Select::make('schedule_frequency')
            ->label(__('export-scheduler::scheduler.schedule_frequency'))
            ->placeholder(__('export-scheduler::scheduler.schedule_time_hint'))
            ->hintIcon('heroicon-m-question-mark-circle', tooltip: __('export-scheduler::scheduler.schedule_time_hint'))
            ->options(ScheduleFrequency::selectArray())
            ->required()
            ->native(false)
            ->reactive()
            ->afterStateUpdated(function (Set $set, $state) {
                // Reset dependent fields when frequency changes
                if ($state !== ScheduleFrequency::YEARLY->value) {
                    $set('schedule_month', null);
                }
                if (!in_array(
                    $state,
                    [ScheduleFrequency::MONTHLY->value, ScheduleFrequency::QUARTERLY->value, ScheduleFrequency::HALF_YEARLY->value,
                        ScheduleFrequency::YEARLY->value]
                )) {
                    $set('schedule_day_of_month', null);
                }
                if ($state !== ScheduleFrequency::WEEKLY->value) {
                    $set('schedule_day_of_week', null);
                }
                if ($state !== ScheduleFrequency::CRON->value) {
                    $set('custom_cron_expression', null);
                }
            });
    }


    /**
     * @return TextInput
     */
    public static function customCronExpression(): TextInput
    {
        return TextInput::make('custom_cron_expression')
            ->label(__('export-scheduler::scheduler.custom_cron_expression'))
            ->visible(fn(Get $get) => ScheduleFrequency::CRON->is($get('schedule_frequency')))
            ->required(fn(Get $get) => ScheduleFrequency::CRON->is($get('schedule_frequency')))
            ->hintColor('info')
            ->placeholder("eg 0 0 * * 0 ")
            ->rules([
                fn(): Closure => function (string $attribute, $value, \Closure $fail) {
                    if (!ExportScheduler::isValidCronExpression($value)) {
                        $fail(__('Invalid cron expression'));
                    }
                },
            ]);
    }

    public static function cronHint(): Placeholder
    {
        return Placeholder::make('cron_hint')
            ->visible(fn(Get $get) => ScheduleFrequency::CRON->is($get('schedule_frequency')))
            ->label(__('Cron Tips'))
            ->content(new HtmlString("<div style='line-height: 1.7'><p>".__('export-scheduler::scheduler.cron_expression_hint')."</p></div>"));
    }

    /**
     * @return Select
     */
    public static function scheduleDayOfWeek(): Select
    {
        return Select::make('schedule_day_of_week')
            ->label(__('export-scheduler::scheduler.schedule_day_of_week'))
            ->placeholder(__('export-scheduler::scheduler.schedule_day_of_week_placeholder'))
            ->options(DayOfWeek::class)
            ->native(false)
            ->nullable()
            ->visible(fn(Get $get) => $get('schedule_frequency') === ScheduleFrequency::WEEKLY->value)
            ->required(fn(Get $get) => $get('schedule_frequency') === ScheduleFrequency::WEEKLY->value);
    }

    /**
     * @return Select
     */
    public static function scheduleDayOfMonth(): Select
    {
        return Select::make('schedule_day_of_month')
            ->label(__('export-scheduler::scheduler.schedule_day_of_month'))
            ->placeholder(__('export-scheduler::scheduler.schedule_day_of_month_placeholder'))
            ->options(array_replace(
                array_combine(range(1, 31), range(1, 31)), // Ensure correct keys and values
                ['-1' => __('Last day of the month')]      // Add the 'Last day of the month' option
            ))
            ->native(false)
            ->nullable()
            ->visible(fn(Get $get) => Helper::isDayOfMonthFieldRequired($get))
            ->required(fn(Get $get) => Helper::isDayOfMonthFieldRequired($get));
    }

    /**
     * @return Select
     */
    public static function scheduleMonth(): Select
    {
        return Select::make('schedule_month')
            ->label(__('export-scheduler::scheduler.schedule_month'))
            ->placeholder(__('export-scheduler::scheduler.schedule_month_placeholder'))
            ->options(Month::class)
            ->native(false)
            ->nullable()
            ->visible(fn(Get $get) => $get('schedule_frequency') === ScheduleFrequency::YEARLY->value)
            ->required(fn(Get $get) => $get('schedule_frequency') === ScheduleFrequency::YEARLY->value);
    }

    /**
     * @return Select
     */
    public static function scheduleStartMonth(): Select
    {
        return Select::make('schedule_start_month')
            ->label(__('export-scheduler::scheduler.schedule_start_month'))
            ->placeholder(__('export-scheduler::scheduler.schedule_start_month_placeholder'))
            ->options(Month::class)
            ->native(false)
            ->nullable()
            ->visible(fn(Get $get) => Helper::isStartDateRequired($get))
            ->required(fn(Get $get) => Helper::isStartDateRequired($get));
    }


    /**
     * @return Select
     */
    public static function scheduleTimeZone(): Select
    {
        return Select::make('schedule_timezone')
            ->label(__('export-scheduler::scheduler.schedule_timezone'))
            ->options(timezone_identifiers_list())
            ->searchable()
            ->native(false)
            ->default(config('app.timezone'));
    }

    /**
     * @return TimePicker
     */
    public static function scheduleTime(): TimePicker
    {
        return TimePicker::make('schedule_time')
            ->seconds(false)
            ->label(__('export-scheduler::scheduler.schedule_time'))
            ->visible(fn(Get $get) => $get('schedule_frequency') !== ScheduleFrequency::CRON->value)
            ->required(fn(Get $get) => $get('schedule_frequency') !== ScheduleFrequency::CRON->value)
            ->default('00:00');
    }


    /**
     * @return Select
     */
    public static function dateRange(): Select
    {
        return Select::make('date_range')
            ->placeholder(__('export-scheduler::scheduler.date_range_placeholder'))
            ->hintIcon('heroicon-m-question-mark-circle', tooltip: __('export-scheduler::scheduler.date_range_tooltip'))
            ->hintColor('info')
            ->label(__('export-scheduler::scheduler.date_range'))
            ->options(DateRange::selectArray())
            ->native(false);
    }


    /**
     * @return Select
     */
    public static function formats(): Select
    {
        return Select::make('formats')
            ->label(__('export-scheduler::scheduler.formats'))
            ->options([
                'csv'  => __('CSV'),
                'xlsx' => __('XLSX'),
            ])
            ->default([ExportFormat::Xlsx])
            ->native(false)
            ->multiple()
            ->required();
    }


    /**
     * @return Repeater
     */
    public static function columnsRepeater(): Repeater
    {
        return Repeater::make('columns')
            ->label(__('export-scheduler::scheduler.columns'))
            ->columns(2)
            ->columnSpan(3)
            ->addable(false)
            ->collapsed()
            ->deleteAction(
                fn(Action $action) => $action
                    ->label('Remove')
                    ->button(),
            )
            ->afterStateUpdated(function (?ExportSchedule $record, $state, Get $get, Set $set) {
                $allColumns = ExportSchedule::getDefaultColumnsForExporter($get('exporter') ?? "");
                $currentColumnNames = collect($state)->pluck('name')->all();
                $newAvailableColumns = $allColumns->reject(function ($column) use ($currentColumnNames) {
                    return in_array($column['name'], $currentColumnNames);
                });
                $set('available_columns', $newAvailableColumns->values()->all());

            })
            ->live()
            ->itemLabel(fn(array $state): ?string => $state['label'] ?? null)
            ->maxItems(fn(Get $get) => $get('exporter') ? ExportSchedule::getDefaultColumnsForExporter($get('exporter'))->count() : 0)
            ->schema([
                TextInput::make('name')->visible(false),
                TextInput::make('label'),
            ])->default(fn(Get $get) => ExportSchedule::getDefaultColumnsForExporter($get('exporter') ?? "")->toArray());
    }


    /**
     * @return Repeater
     */
    public static function availableColumns(): Repeater
    {
        return Repeater::make('available_columns')
            ->schema([
                Hidden::make('name')->visible(false),
                TextInput::make('label')->label(false),
            ])
            ->label(__('export-scheduler::scheduler.available_columns'))
            ->columns(2)
            ->columnSpan(1)
            ->collapsed()
            ->live()
            ->reorderable(false)
            ->deleteAction(function (Action $action) {
                return $action
                    ->label('Add')
                    ->color('success')
                    ->icon('heroicon-o-plus')
                    ->button()
                    ->after(function ($state, Get $get, Set $set) {
                        // Fetch all default columns as a collection using the static method
                        $allColumns = ExportSchedule::getDefaultColumnsForExporter($state);
                        $currentSelectedColumns = $get('columns'); // Current selected columns
                        $currentAvailableColumns = $state;         // Current available columns from state
                        $combinedCurrentColumns = collect($currentSelectedColumns)
                            ->merge($currentAvailableColumns)
                            ->pluck('name')
                            ->all();

                        // Identify the deleted item by comparing with all columns
                        $deletedItem = $allColumns->reject(function ($column) use ($combinedCurrentColumns) {
                            return in_array($column['name'], $combinedCurrentColumns);
                        })->first();

                        if ($deletedItem) {
                            $deletedItemKey = (string) Str::uuid(); // Generate a unique key
                            $newColumns = [$deletedItemKey => $deletedItem];
                            $updatedColumns = $currentSelectedColumns + $newColumns;
                            $set('columns', $updatedColumns); // Update the columns
                        }
                    });
            })
            ->itemLabel(fn(array $state): ?string => $state['label'] ?? null)
            ->maxItems(fn(Get $get) => $get('exporter') ? ExportSchedule::getDefaultColumnsForExporter($get('exporter'))->count() : 0)
            ->addable(false)
            ->formatStateUsing(function (Get $get) {
                $allColumns = ExportSchedule::getDefaultColumnsForExporter($get('exporter') ?? "");
                $currentColumnNames = collect($get('columns'))->pluck('name')->all();
                $availableColumns = $allColumns->reject(function ($column) use ($currentColumnNames) {
                    return in_array($column['name'], $currentColumnNames);
                });
                return $availableColumns->toArray();
            });
    }


}
