<?php

namespace VisualBuilder\ExportScheduler\Resources;

use Closure;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use VisualBuilder\ExportScheduler\Enums\DateRange;
use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;
use VisualBuilder\ExportScheduler\ExportSchedulerPlugin;
use VisualBuilder\ExportScheduler\Facades\ExportScheduler;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;
use VisualBuilder\ExportScheduler\Resources\ExportScheduleResource\Pages;
use VisualBuilder\ExportScheduler\Support\ColumnHelper;
use VisualBuilder\ExportScheduler\Support\MorphToSelectHelper;

class ExportScheduleResource extends Resource
{
    protected static ?string $model = ExportSchedule::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    public static function shouldRegisterNavigation(): bool
    {
        return ExportSchedulerPlugin::get()->shouldRegisterNavigation();
    }

    public static function getNavigationGroup(): string
    {
        return config('export-scheduler.navigation.group');
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
        return __(config('export-scheduler.navigation.label'));
    }

    public static function getCluster(): string
    {
        return config('export-scheduler.navigation.cluster');
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return config('export-scheduler.navigation.position');
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
                                ->afterStateUpdated(fn (callable $set, $state) => $set('columns', $state ? ColumnHelper::getDefaultColumns($state) : []))
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
                                Select::make('schedule_frequency')
                                    ->label(__('export-scheduler::scheduler.schedule_frequency'))
                                    ->placeholder(__('export-scheduler::scheduler.schedule_time_hint'))
                                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: __('export-scheduler::scheduler.schedule_time_hint'))
                                    ->options(ScheduleFrequency::selectArray())
                                    ->default(ScheduleFrequency::MONTHLY->value)
                                    ->required()
                                    ->native(false)
                                    ->reactive()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        // Reset dependent fields when frequency changes
                                        if ($state !== ScheduleFrequency::YEARLY->value) {
                                            $set('schedule_month', null);
                                        }
                                        if (! in_array(
                                            $state,
                                            [ScheduleFrequency::MONTHLY->value, ScheduleFrequency::QUARTERLY->value, ScheduleFrequency::HALF_YEARLY->value, ScheduleFrequency::YEARLY->value]
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

                                TextInput::make('custom_cron_expression')
                                    ->label(__('export-scheduler::scheduler.custom_cron_expression'))
                                    ->visible(fn (Get $get) => ScheduleFrequency::CRON->is($get('schedule_frequency')))
                                    ->required(fn (Get $get) => ScheduleFrequency::CRON->is($get('schedule_frequency')))
                                    ->rules([
                                        fn (): Closure => function (string $attribute, $value, Closure $fail) {
                                            if (! ExportScheduler::isValidCronExpression($value)) {
                                                $fail(__('Invalid cron expression'));
                                            }
                                        },
                                    ]),

                                Select::make('schedule_day_of_week')
                                    ->label(__('export-scheduler::scheduler.schedule_day_of_week'))
                                    ->options([
                                        'Monday' => __('Monday'),
                                        'Tuesday' => __('Tuesday'),
                                        'Wednesday' => __('Wednesday'),
                                        'Thursday' => __('Thursday'),
                                        'Friday' => __('Friday'),
                                        'Saturday' => __('Saturday'),
                                        'Sunday' => __('Sunday'),
                                    ])
                                    ->native(false)
                                    ->visible(fn (Get $get) => $get('schedule_frequency') === ScheduleFrequency::WEEKLY->value)
                                    ->nullable(),

                                Select::make('schedule_day_of_month')
                                    ->label(__('export-scheduler::scheduler.schedule_day_of_month'))
                                    ->options(range(1, 31) + ['-1' => __('Last day of the month')])
                                    ->native(false)
                                    ->visible(fn (Get $get) => in_array(
                                        $get('schedule_frequency'),
                                        [ScheduleFrequency::MONTHLY->value, ScheduleFrequency::QUARTERLY->value, ScheduleFrequency::HALF_YEARLY->value, ScheduleFrequency::YEARLY->value]
                                    ))
                                    ->nullable(),

                                Select::make('schedule_month')
                                    ->label(__('export-scheduler::scheduler.schedule_month'))
                                    ->options([
                                        'January' => __('January'),
                                        'February' => __('February'),
                                        'March' => __('March'),
                                        'April' => __('April'),
                                        'May' => __('May'),
                                        'June' => __('June'),
                                        'July' => __('July'),
                                        'August' => __('August'),
                                        'September' => __('September'),
                                        'October' => __('October'),
                                        'November' => __('November'),
                                        'December' => __('December'),
                                    ])
                                    ->native(false)
                                    ->visible(fn (Get $get) => $get('schedule_frequency') === ScheduleFrequency::YEARLY->value)
                                    ->nullable(),

                                Grid::make()
                                    ->schema([
                                        TimePicker::make('schedule_time')
                                            ->seconds(false)
                                            ->label(__('export-scheduler::scheduler.schedule_time'))
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
                                    ->label(__('export-scheduler::scheduler.date_range'))
                                    ->options(DateRange::selectArray())
                                    ->native(false)
                                    ->required(),

                            ]),

                        Section::make('File Format')
                            ->schema([
                                Select::make('formats')
                                    ->label(__('export-scheduler::scheduler.formats'))
                                    ->options([
                                        'csv' => __('CSV'),
                                        'xlsx' => __('XLSX'),
                                    ])
                                    ->default([ExportFormat::Xlsx])
                                    ->native(false)
                                    ->multiple()
                                    ->required(),

                            ]),

                    ]),
                    Tabs\Tab::make('Columns')
                        ->schema([
                            Repeater::make('columns')
                                ->label(__('export-scheduler::scheduler.columns'))
                                ->addable(false)
                                ->columns(3)
                                ->columnSpanFull()
                                ->schema([
                                    TextInput::make('name')->required(),
                                    TextInput::make('label'),
                                ])->default(fn (Get $get) => $get('exporter') ? ColumnHelper::getDefaultColumns($get('exporter')) : []),

                        ]),
                ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('schedule_frequency'),
                Tables\Columns\TextColumn::make('date_range'),
                Tables\Columns\TextColumn::make('owner.email'),
                Tables\Columns\TextColumn::make('last_run_at')->label('Last Run')->date(),
                Tables\Columns\TextColumn::make('last_successful_run_at')->label('Last Success')->date(),
                Tables\Columns\TextColumn::make('next_due_at')->label('Next Due')->date(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListExportSchedules::route('/'),
            'create' => Pages\CreateExportSchedule::route('/create'),
            'edit' => Pages\EditExportSchedule::route('/{record}/edit'),
        ];
    }
}
