<?php

namespace Visualbuilder\ExportScheduler\Filament\Resources;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
use Visualbuilder\ExportScheduler\Contracts\BypassesReportVisibility;
use Visualbuilder\ExportScheduler\Contracts\ResolvesReportUsers;
use Visualbuilder\ExportScheduler\ExportSchedulerPlugin;
use Visualbuilder\ExportScheduler\Filament\Actions\Tables\RunExport;
use Visualbuilder\ExportScheduler\Filament\Forms\ScheduleFields;
use Visualbuilder\ExportScheduler\Filament\Resources\ScheduledReportResource\Pages;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;

class ScheduledReportResource extends Resource
{
    protected static ?string $model = ScheduledReport::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string | UnitEnum | null $navigationGroup = 'Reports';

    public static function shouldRegisterNavigation(): bool
    {
        return ExportSchedulerPlugin::get()->shouldRegisterScheduleNavigation();
    }

    public static function getNavigationGroup(): ?string
    {
        return config('export-scheduler.navigation.schedules.group');
    }

    public static function getNavigationIcon(): string | BackedEnum | null
    {
        return config('export-scheduler.navigation.schedules.icon');
    }

    public static function getNavigationSort(): ?int
    {
        return config('export-scheduler.navigation.schedules.sort');
    }

    public static function getModelLabel(): string
    {
        return __(config('export-scheduler.navigation.schedules.label'));
    }

    public static function getPluralModelLabel(): string
    {
        return __(config('export-scheduler.navigation.schedules.plural_label'));
    }

    public static function getCluster(): ?string
    {
        return config('export-scheduler.navigation.schedules.cluster') ?: null;
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return config('export-scheduler.navigation.schedules.position') ?? SubNavigationPosition::Top;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(ScheduleFields::schema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('report.name')
                    ->label(__('export-scheduler::scheduler.report'))
                    ->sortable()
                    ->url(fn (ScheduledReport $record) => CustomReportResource::getUrl('view', ['record' => $record->report->getKey()])),
                TextColumn::make('schedule_frequency')
                    ->label(__('export-scheduler::scheduler.schedule_frequency'))
                    ->badge(),
                TextColumn::make('recipient')
                    ->label(__('export-scheduler::scheduler.recipient'))
                    ->state(function (ScheduledReport $record) {
                        if (! $record->recipient) {
                            return null;
                        }

                        return app(ResolvesReportUsers::class)->getLabel($record->recipient);
                    }),
                TextColumn::make('next_run_at')
                    ->label(__('export-scheduler::scheduler.next_run_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_run_at')
                    ->label(__('export-scheduler::scheduler.last_run'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('last_successful_run_at')
                    ->label(__('export-scheduler::scheduler.last_success'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('enabled')
                    ->label(__('export-scheduler::scheduler.enabled')),
            ])
            ->recordActions([
                RunExport::make('run'),
                EditAction::make(),
                DeleteAction::make()
                    ->modalHeading(__('export-scheduler::scheduler.delete_scheduled_report'))
                    ->modalDescription(__('export-scheduler::scheduler.confirm_delete_scheduled_report')),
            ])
            ->headerActions([
                DeleteBulkAction::make()
                    ->modalHeading(__('export-scheduler::scheduler.bulk_delete_scheduled_report'))
                    ->modalDescription(__('export-scheduler::scheduler.confirm_bulk_delete_scheduled_report')),
            ]);
    }

    /**
     * Only schedules on reports you own. A report shared with you is readable,
     * but its delivery configuration is not yours to see or change.
     *
     * Can be overridden via BypassesReportVisibility contract.
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->with('report')
            ->whereHas('report', function (Builder $q) use ($user) {
                if (! $user) {
                    return $q->whereRaw('1 = 0');
                }

                // Check if user can bypass visibility restrictions
                if (app(BypassesReportVisibility::class)->can($user)) {
                    return $q;
                }

                return $q->where('owner_type', $user::class)->where('owner_id', $user->getKey());
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListScheduledReports::route('/'),
            'create' => Pages\CreateScheduledReport::route('/create'),
            'edit' => Pages\EditScheduledReport::route('/{record}/edit'),
        ];
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return $record->report?->isOwnedBy($user)
            || app(BypassesReportVisibility::class)->can($user);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        return $record->report?->isOwnedBy($user)
            || app(BypassesReportVisibility::class)->can($user);
    }
}
