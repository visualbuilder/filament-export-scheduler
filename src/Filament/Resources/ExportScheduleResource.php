<?php

namespace VisualBuilder\ExportScheduler\Filament\Resources;


use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use VisualBuilder\ExportScheduler\ExportSchedulerPlugin;
use VisualBuilder\ExportScheduler\Filament\Actions\Tables\RunExport;
use VisualBuilder\ExportScheduler\Filament\Forms\Fields;
use VisualBuilder\ExportScheduler\Filament\Resources\ExportScheduleResource\Pages;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;

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
                            Fields::name(),
                            Fields::exporter(),
                        ])->columns(1)->columnSpan(1),

                        Grid::make()->schema([
                            Fields::ownerMorphSelect(),
                        ])->columns(1)->columnSpan(1),

                        Fields::copyToUser()

                    ])->columns(2),

                    Tabs\Tab::make('Schedule')->schema([
                        Section::make('When to Run')
                            ->schema([

                                Grid::make()->schema([
                                    Fields::scheduleFrequency(),
                                    Fields::enableToggle()
                                ])->columns(2),

                                Grid::make()->schema([
                                    Fields::customCronExpression(),
                                    Fields::cronHint(),
                                ])->columns(2),

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
                        ->visible(fn(Get $get) => $get('exporter') ? ExportSchedule::getDefaultColumnsForExporter($get('exporter'))->count() : false)
                        ->extraAttributes(['class' => 'column_picker'])

                ])->persistTab()
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
// todo
//                Tables\Columns\TextColumn::make('next_due_at')->label(__('export-scheduler::scheduler.next_due'))->date(),
                Tables\Columns\ToggleColumn::make('enabled')->label(__('export-scheduler::scheduler.enabled')),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                RunExport::make('run'),

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


}
