<?php

namespace Visualbuilder\ExportScheduler\Filament\Resources;

use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
use Visualbuilder\ExportScheduler\Contracts\ResolvesReportUsers;
use Visualbuilder\ExportScheduler\ExportSchedulerPlugin;
use Visualbuilder\ExportScheduler\Filament\Actions\Tables\DownloadExport;
use Visualbuilder\ExportScheduler\Filament\Forms\ReportFields;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\Pages;
use Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\RelationManagers\SchedulesRelationManager;
use Visualbuilder\ExportScheduler\Models\CustomReport;

class CustomReportResource extends Resource
{
    protected static ?string $model = CustomReport::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string | UnitEnum | null $navigationGroup = 'Reports';

    public static function shouldRegisterNavigation(): bool
    {
        return ExportSchedulerPlugin::get()->shouldRegisterReportNavigation();
    }

    public static function getNavigationGroup(): ?string
    {
        return config('export-scheduler.navigation.reports.group');
    }

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return config('export-scheduler.navigation.reports.icon');
    }

    public static function getNavigationSort(): ?int
    {
        return config('export-scheduler.navigation.reports.sort');
    }

    public static function getModelLabel(): string
    {
        return __(config('export-scheduler.navigation.reports.label'));
    }

    public static function getPluralModelLabel(): string
    {
        return __(config('export-scheduler.navigation.reports.plural_label'));
    }

    public static function getCluster(): ?string
    {
        return config('export-scheduler.navigation.reports.cluster') ?: null;
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return config('export-scheduler.navigation.reports.position') ?? SubNavigationPosition::Top;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(ReportFields::schema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('export-scheduler::scheduler.name'))
                    ->sortable(),
                TextColumn::make('report_type')->label('Type')->badge(),
                TextColumn::make('date_range')
                    ->label(__('export-scheduler::scheduler.date_range'))
                    ->badge()
                    ->color('warning'),
                TextColumn::make('visibility')
                    ->label(__('export-scheduler::scheduler.visibility'))
                    ->badge(),
                TextColumn::make('owner')
                    ->label(__('export-scheduler::scheduler.owner'))
                    ->state(fn (CustomReport $record) => $record->owner
                        ? app(ResolvesReportUsers::class)->getLabel($record->owner)
                        : null),
                TextColumn::make('schedules_count')
                    ->label(__('export-scheduler::scheduler.schedules'))
                    ->counts('schedules')
                    ->badge(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                // No Run here. A report may carry several schedules with different
                // recipients, so "run this report" has no single meaning — running is
                // a property of a schedule. Download is the report-level equivalent:
                // ad hoc, and returned to whoever asked for it.
                ViewAction::make(),
                DownloadExport::make('download'),
                EditAction::make()
                    ->visible(fn (CustomReport $record): bool => $record->isOwnedBy(auth()->user())),
            ])
            ->headerActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * A report you cannot see is a report that does not exist, for every page of
     * this resource at once.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function getRelations(): array
    {
        return [
            SchedulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomReports::route('/'),
            'create' => Pages\CreateCustomReport::route('/create'),
            'view' => Pages\ViewCustomReport::route('/{record}/view'),
            'edit' => Pages\EditCustomReport::route('/{record}/edit'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Visibility grants read access only. Everything that changes the report, or
    | fires a delivery at somebody else, stays with the owner.
    */

    public static function canView(Model $record): bool
    {
        return $record->isVisibleTo(auth()->user());
    }

    public static function canEdit(Model $record): bool
    {
        return $record->isOwnedBy(auth()->user());
    }

    public static function canDelete(Model $record): bool
    {
        return $record->isOwnedBy(auth()->user());
    }
}
