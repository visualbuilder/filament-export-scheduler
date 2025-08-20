<?php

namespace Visualbuilder\ExportScheduler\Filament\Resources {

    use Filament\Actions\BulkActionGroup;
    use Filament\Actions\DeleteBulkAction;
    use Filament\Actions\EditAction;
    use Filament\Pages\Enums\SubNavigationPosition;
    use Filament\Resources\Resource;
    use Filament\Schemas\Components\Grid;
    use Filament\Schemas\Components\Section;
    use Filament\Schemas\Components\Tabs;
    use Filament\Schemas\Components\Utilities\Get;
    use Filament\Schemas\Schema;
    use Filament\Tables;
    use Filament\Tables\Table;
    use Visualbuilder\ExportScheduler\ExportSchedulerPlugin;
    use Visualbuilder\ExportScheduler\Filament\Actions\Tables\RunExport;
    use Visualbuilder\ExportScheduler\Filament\Forms\Fields;
    use Visualbuilder\ExportScheduler\Filament\Resources\ExportScheduleResource\Pages;
    use Visualbuilder\ExportScheduler\Models\ExportSchedule;

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

        public static function form(Schema $schema): Schema
        {
            return $schema
                ->schema([
                    Tabs::make('tabs')->tabs([
                        Tabs\Tab::make('Exporter')
                            ->schema([
                                Section::make()
                                    ->columnSpanFull()
                                    ->schema([
                                        Grid::make()
                                            ->schema([
                                                Fields::name(),
                                                Fields::exporter(),
                                            ])
                                            ->columns(1)
                                            ->columnSpan(1),

                                        Grid::make()
                                            ->schema([
                                                Fields::ownerMorphSelect(),
                                            ])
                                            ->columns(1)
                                            ->columnSpan(1),

                                        Fields::copyToUser(),
                                        Fields::automaticRecipients(),
                                    ])->columns(),

                                Fields::filterByAttributeSection(),
                                Fields::filterReportSection(),
                            ]),

                        Tabs\Tab::make('Schedule')->schema([
                            Section::make('When to Run')
                                ->schema([
                                    Grid::make()->schema([
                                        Fields::scheduleFrequency(),
                                        Fields::enableToggle(),
                                    ])->columns(),

                                    Grid::make()->schema([
                                        Fields::customCronExpression(),
                                        Fields::cronHint(),
                                    ])->columns(),

                                    Fields::scheduleDayOfWeek(),
                                    Fields::scheduleDayOfMonth(),
                                    Fields::scheduleMonth(),
                                    Fields::scheduleStartMonth(),

                                    Grid::make()
                                        ->schema([
                                            Fields::scheduleTime(),
                                            Fields::scheduleTimeZone(),
                                        ]),
                                ]),

                            Section::make('Query Date Range')
                                ->schema([
                                    Fields::dateRange(),
                                ]),

                            Section::make('File Format')
                                ->schema([
                                    Fields::formats(),
                                ]),
                        ]),

                        Tabs\Tab::make('Columns')
                            ->schema([
                                Fields::availableColumns(),
                                Fields::columnsRepeater(),
                            ])
                            ->columns(4)
                            ->visible(fn (Get $get) => $get('exporter') ? ExportSchedule::getDefaultColumnsForExporter($get('exporter'))->count() : false)
                            ->extraAttributes(['class' => 'column_picker']),
                    ])
                        ->contained(false)
                        ->persistTab()
                        ->persistTabInQueryString()
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
                    Tables\Columns\TextColumn::make('next_run_at')->label(__('export-scheduler::scheduler.next_run_at'))->date(),
                    Tables\Columns\ToggleColumn::make('enabled')->label(__('export-scheduler::scheduler.enabled')),
                ])
                ->recordActions([
                    EditAction::make(),
                    RunExport::make('run'),
                ])
                ->headerActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
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
}
