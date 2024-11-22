<?php

namespace VisualBuilder\ExportScheduler\Filament\Resources;

use App\Models\EndUser;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;
use VisualBuilder\ExportScheduler\ExportSchedulerPlugin;
use VisualBuilder\ExportScheduler\Filament\Forms\Fields;
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
                            Fields::name(),
                            Fields::exporter(),
                        ])->columns(1)->columnSpan(1),
                        Grid::make()->schema([
                            MorphToSelectHelper::createMorphToSelect(
                                label: __('export-scheduler::scheduler.owner')
                            ),
                        ])->columns(1)->columnSpan(1),

                        Fieldset::make(__('export-scheduler::scheduler.cc'))
                            ->schema([
                                Placeholder::make('Data Security Warning')
                                    ->content(fn(Get $get) =>new HtmlString( __('export-scheduler::scheduler.cc_warning',['owner_type'=>class_basename($get('owner_type'))]))),

                                Repeater::make('cc')
                                    ->label('')
                                    ->addActionLabel(__('export-scheduler::scheduler.cc_add_label'))
                                    ->simple(
                                        Select::make('id')
                                            ->label('User')
                                            ->options(function ($get) {
                                                $type = $get('../../owner_type');
                                                $ownerId = $get('../../owner_id');
                                                $ccItems = $get('../../cc');
                                                $ccIds = [];
                                                if (is_array($ccItems)) {
                                                    $ccIds = collect($ccItems)->pluck('id')->toArray();
                                                }
                                                $excludeIds = array_filter(array_merge($ccIds, [$ownerId]));
                                                return $type ? $type::query()->whereNotIn('id',$excludeIds)->pluck('email', 'id') : [];
                                            }) ->getOptionLabelUsing(function($value, Get $get){
                                                $type = $get('../../owner_type');
                                                return $type ? $type::query()->find($value)?->email:"";
                                            })
                                            ->placeholder(__('export-scheduler::scheduler.cc_placeholder'))
                                            ->searchable(),
                                    )

                            ])->visible(fn(Get $get) => $get('owner_id'))

                    ])->columns(2),

                    Tabs\Tab::make('Schedule')->schema([
                        Section::make('When to Run')
                            ->schema([
                                Grid::make()->schema([
                                    Fields::scheduleFrequency(),
                                    Toggle::make('enabled')
                                        ->inline(false)
                                        ->label(__('export-scheduler::scheduler.enabled')),
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
                ])->persistTab()->persistTabInQueryString()
                    ->columnSpanFull(),
            ]);
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



}
