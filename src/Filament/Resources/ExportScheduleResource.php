<?php

namespace VisualBuilder\ExportScheduler\Filament\Resources;

use Closure;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use VisualBuilder\ExportScheduler\Enums\DateRange;
use VisualBuilder\ExportScheduler\Enums\DayOfWeek;
use VisualBuilder\ExportScheduler\Enums\Month;
use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;
use VisualBuilder\ExportScheduler\ExportSchedulerPlugin;
use VisualBuilder\ExportScheduler\Facades\ExportScheduler;
use VisualBuilder\ExportScheduler\Filament\Resources\ExportScheduleResource\Pages;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;
use VisualBuilder\ExportScheduler\Services\ScheduledExporter;
use VisualBuilder\ExportScheduler\Support\MorphToSelectHelper;

class ExportScheduleResource extends Resource
{
    protected static ?string $model = ExportSchedule::class;

    public static function shouldRegisterNavigation(): bool
    {
        return ExportSchedulerPlugin::get()->shouldRegisterNavigation();
    }

    public static function getNavigationGroup(): string
    {
        return config('export-scheduler.navigation.group');
    }

    public static function getNavigationIcon(): string
    {
        return config('export-scheduler.navigation.icon');
    }

    public static function getNavigationSort(): ?int
    {
        return config('export-scheduler.navigation.sort');
    }

    public static function getModelLabel(): string
    {
        return __(config('export-scheduler.navigation.label'));
    }

    public static function getPluralModelLabel(): string
    {
        return __(config('export-scheduler.navigation.plural_label'));
    }

    public static function getCluster(): string
    {
        return config('export-scheduler.navigation.cluster');
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return config('export-scheduler.navigation.position') ?? SubNavigationPosition::Top;
    }

    public static function form(Form $form): Form
    {
        return $form
                ->schema([
                        Tabs::make('tabs')->tabs([
                                Tabs\Tab::make('Exporter')->schema([
                                        Grid::make()->schema([
                                                TextInput::make('name')
                                                        ->label(__('export-scheduler::scheduler.name'))
                                                        ->placeholder(__('export-scheduler::scheduler.name_placeholder'))
                                                        ->required()
                                                        ->maxLength(191),

                                                Select::make('exporter')
                                                        ->label(__('export-scheduler::scheduler.exporter'))
                                                        ->hintColor('info')->hintIcon('heroicon-m-question-mark-circle', tooltip: __('export-scheduler::scheduler.exporter_hint'))
                                                        ->hintColor('info')
                                                        ->options(ExportScheduler::listExporters())
                                                        ->native(false)
                                                        ->reactive()
                                                        ->afterStateUpdated(function (?ExportSchedule $record, $state, Set $set, $livewire) {
                                                            /** Update the column definitions when changing exporter */
                                                            $defaultColumns = ExportSchedule::getDefaultColumnsForExporter($state);

                                                            $set('columns', $defaultColumns->toArray() ?? []);
                                                            $set('available_columns', []);

                                                            if ($record) {
                                                                $record->update([
                                                                        'exporter'          => $state,
                                                                        'columns'           => $defaultColumns ?? [],
                                                                ]);
                                                            }

                                                        })
                                                        ->required(),
                                        ])->columns(1)->columnSpan(1),
                                        Grid::make()->schema([
                                                MorphToSelectHelper::createMorphToSelect(
                                                        label: __('export-scheduler::scheduler.owner')
                                                ),
                                        ])->columns(1)->columnSpan(1),

                                ])->columns(2),

                                Tabs\Tab::make('Schedule')->schema([

                                        Section::make('When to Run')
                                                ->schema([
                                                        Grid::make()->schema([
                                                                Select::make('schedule_frequency')
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
                                                                        }),

                                                                Toggle::make('enabled')
                                                                        ->inline(false)
                                                                        ->label(__('export-scheduler::scheduler.enabled')),

                                                        ])->columns(2),

                                                        Grid::make()->schema([
                                                                TextInput::make('custom_cron_expression')
                                                                        ->label(__('export-scheduler::scheduler.custom_cron_expression'))
                                                                        ->visible(fn(Get $get) => ScheduleFrequency::CRON->is($get('schedule_frequency')))
                                                                        ->required(fn(Get $get) => ScheduleFrequency::CRON->is($get('schedule_frequency')))
                                                                        ->hintColor('info')
                                                                        ->placeholder("eg 0 0 * * 0 ")
                                                                        ->rules([
                                                                                fn(): Closure => function (string $attribute, $value, Closure $fail) {
                                                                                    if (!ExportScheduler::isValidCronExpression($value)) {
                                                                                        $fail(__('Invalid cron expression'));
                                                                                    }
                                                                                },
                                                                        ]),
                                                                Placeholder::make('cron_hint')
                                                                        ->visible(fn(Get $get) => ScheduleFrequency::CRON->is($get('schedule_frequency')))
                                                                        ->label(__('Cron Tips'))
                                                                        ->content(new HtmlString("<div style='line-height: 1.7'><p>".__('export-scheduler::scheduler.cron_expression_hint')."</p></div>")),

                                                        ])->columns(2),





                                                        Select::make('schedule_day_of_week')
                                                                ->label(__('export-scheduler::scheduler.schedule_day_of_week'))
                                                                ->placeholder(__('export-scheduler::scheduler.schedule_day_of_week_placeholder'))
                                                                ->options(DayOfWeek::class)
                                                                ->native(false)
                                                                ->nullable()
                                                                ->visible(fn(Get $get) => $get('schedule_frequency') === ScheduleFrequency::WEEKLY->value)
                                                                ->required(fn(Get $get) => $get('schedule_frequency') === ScheduleFrequency::WEEKLY->value),


                                                        Select::make('schedule_day_of_month')
                                                                ->label(__('export-scheduler::scheduler.schedule_day_of_month'))
                                                                ->placeholder(__('export-scheduler::scheduler.schedule_day_of_month_placeholder'))
                                                                ->options(array_replace(
                                                                        array_combine(range(1, 31), range(1, 31)), // Ensure correct keys and values
                                                                        ['-1' => __('Last day of the month')]      // Add the 'Last day of the month' option
                                                                ))
                                                                ->native(false)
                                                                ->nullable()
                                                                ->visible(fn(Get $get) => self::isDayOfMonthFieldRequired($get))
                                                                ->required(fn(Get $get) => self::isDayOfMonthFieldRequired($get)),


                                                        Select::make('schedule_month')
                                                                ->label(__('export-scheduler::scheduler.schedule_month'))
                                                                ->placeholder(__('export-scheduler::scheduler.schedule_month_placeholder'))
                                                                ->options(Month::class)
                                                                ->native(false)
                                                                ->nullable()
                                                                ->visible(fn(Get $get) => $get('schedule_frequency') === ScheduleFrequency::YEARLY->value)
                                                                ->required(fn(Get $get) => $get('schedule_frequency') === ScheduleFrequency::YEARLY->value),


                                                    Select::make('schedule_start_month')
                                                        ->label(__('export-scheduler::scheduler.schedule_start_month'))
                                                        ->placeholder(__('export-scheduler::scheduler.schedule_start_month_placeholder'))
                                                        ->options(Month::class)
                                                        ->native(false)
                                                        ->nullable()
                                                        ->visible(fn(Get $get) => self::isStartDateRequired($get))
                                                        ->required(fn(Get $get) => self::isStartDateRequired($get)),

                                                        Grid::make()
                                                                ->schema([
                                                                        TimePicker::make('schedule_time')
                                                                                ->seconds(false)
                                                                                ->label(__('export-scheduler::scheduler.schedule_time'))
                                                                                ->visible(fn(Get $get) => $get('schedule_frequency') !== ScheduleFrequency::CRON->value)
                                                                                ->required(fn(Get $get) => $get('schedule_frequency') !== ScheduleFrequency::CRON->value)
                                                                                ->default('00:00'),

                                                                        Select::make('schedule_timezone')
                                                                                ->label(__('export-scheduler::scheduler.schedule_timezone'))
                                                                                ->options(timezone_identifiers_list())
                                                                                ->searchable()
                                                                                ->native(false)
                                                                                ->default(config('app.timezone')),
                                                                ]),
                                                ]),

                                        Section::make('Query Date Range')
                                                ->schema([

                                                        Select::make('date_range')
                                                                ->placeholder(__('export-scheduler::scheduler.date_range_placeholder'))
                                                                ->hintIcon('heroicon-m-question-mark-circle', tooltip: __('export-scheduler::scheduler.date_range_tooltip'))
                                                                ->hintColor('info')
                                                                ->label(__('export-scheduler::scheduler.date_range'))
                                                                ->options(DateRange::selectArray())
                                                                ->native(false),

                                                ]),

                                        Section::make('File Format')
                                                ->schema([
                                                        Select::make('formats')
                                                                ->label(__('export-scheduler::scheduler.formats'))
                                                                ->options([
                                                                        'csv'  => __('CSV'),
                                                                        'xlsx' => __('XLSX'),
                                                                ])
                                                                ->default([ExportFormat::Xlsx])
                                                                ->native(false)
                                                                ->multiple()
                                                                ->required(),

                                                ]),

                                ]),

                                Tabs\Tab::make('Columns')
                                        ->visible(fn(Get $get) => $get('exporter') ? ExportSchedule::getDefaultColumnsForExporter($get('exporter'))->count() : false)
                                        ->extraAttributes(['class' => 'column_picker'])
                                        ->schema([
                                                Repeater::make('available_columns')
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
                                                        ->formatStateUsing(function(Get $get) {
                                                            $allColumns = ExportSchedule::getDefaultColumnsForExporter($get('exporter')??"");
                                                            $currentColumnNames = collect($get('columns'))->pluck('name')->all();
                                                            $availableColumns = $allColumns->reject(function ($column) use ($currentColumnNames) {
                                                                return in_array($column['name'], $currentColumnNames);
                                                            });
                                                            return $availableColumns->toArray();
                                                        }),

                                                Repeater::make('columns')
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
                                                            $allColumns = ExportSchedule::getDefaultColumnsForExporter($get('exporter')??"");
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
                                                        ])->default(fn(Get $get) => ExportSchedule::getDefaultColumnsForExporter($get('exporter')??"")->toArray()),
                                        ])->columns(4),
                        ])->persistTab()->persistTabInQueryString()
                                ->columnSpanFull(),
                ]);
    }

    public static function table(Table $table): Table
    {
        return $table
                ->columns([
                        Tables\Columns\TextColumn::make('id'),
                        Tables\Columns\TextColumn::make('name')->label(__('export-scheduler::scheduler.name')),
                        Tables\Columns\TextColumn::make('schedule_frequency')->label(__('export-scheduler::scheduler.schedule_frequency'))->badge(),
                        Tables\Columns\TextColumn::make('date_range')->label(__('export-scheduler::scheduler.date_range'))->badge()->color('warning'),
                        Tables\Columns\TextColumn::make('owner.email')->label(__('export-scheduler::scheduler.recipient')),
                        Tables\Columns\TextColumn::make('last_run_at')->label(__('export-scheduler::scheduler.last_run'))->date(),
                        Tables\Columns\TextColumn::make('last_successful_run_at')->label(__('export-scheduler::scheduler.last_success'))->date(),
                        Tables\Columns\TextColumn::make('next_due_at')->label(__('export-scheduler::scheduler.next_due'))->date(),
                        Tables\Columns\ToggleColumn::make('enabled')->label(__('export-scheduler::scheduler.enabled')),

                ])
                ->actions([
                        Tables\Actions\EditAction::make(),
                        Tables\Actions\Action::make('run')
                                ->icon('heroicon-s-play')
                                ->color('success')
                                ->requiresConfirmation(fn(ExportSchedule $record) => $record->willLogoutUser())
                                ->modalHeading(fn(ExportSchedule $record) => $record->willLogoutUser() ? __("export-scheduler::scheduler.run_modal_heading") : false)
                                ->modalDescription(fn(ExportSchedule $record) => $record->willLogoutUser()
                                        ? new HtmlString("<p style='line-height: 2'>".__('export-scheduler::scheduler.logout_warning')."</p>")
                                        : false)
                                ->modalSubmitActionLabel(fn(ExportSchedule $record) => $record->willLogoutUser() ? __('export-scheduler::scheduler.run_export') : false)
                                ->modalFooterActionsAlignment(Alignment::End)
                                ->action(function ($record) {
                                    $exporter = new ScheduledExporter($record);
                                    $exporter->run();
                                    Notification::make()
                                            ->title(__('export-scheduler::scheduler.notification_title', ['name' => $record->name]))
                                            ->body(trans_choice('export-scheduler::scheduler.started.body', $exporter->getTotalRows(), [
                                                    'count' => Number::format($exporter->getTotalRows()),
                                            ]))
                                            ->success()
                                            ->send();
                                }),

                ])
                ->bulkActions([
                        Tables\Actions\BulkActionGroup::make([
                                Tables\Actions\DeleteBulkAction::make(),
                        ]),
                ]);
    }

    public static function getPages(): array
    {
        return [
                'index'  => Pages\ListExportSchedules::route('/'),
                'create' => Pages\CreateExportSchedule::route('/create'),
                'edit'   => Pages\EditExportSchedule::route('/{record}/edit'),
        ];
    }

    public static function isDayOfMonthFieldRequired(Get $get): bool
    {
        return in_array(
            $get('schedule_frequency'),
            [
                ScheduleFrequency::MONTHLY->value,
                ScheduleFrequency::QUARTERLY->value,
                ScheduleFrequency::HALF_YEARLY->value,
                ScheduleFrequency::YEARLY->value,
            ]
        );
    }

    public static function isStartDateRequired(Get $get): bool
    {
        return in_array(
                $get('schedule_frequency'),
                [

                        ScheduleFrequency::QUARTERLY->value,
                        ScheduleFrequency::HALF_YEARLY->value,
                ]
        );
    }
}
