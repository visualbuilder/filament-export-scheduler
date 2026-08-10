<?php

namespace Visualbuilder\ExportScheduler\Filament\Resources\CustomReportResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Visualbuilder\ExportScheduler\Contracts\ResolvesReportUsers;
use Visualbuilder\ExportScheduler\Filament\Forms\ScheduleFields;
use Visualbuilder\ExportScheduler\Models\ScheduledReport;

/**
 * Where the old Schedule tab went.
 *
 * The header CreateAction *is* the Schedule action: one modal, whose body is the
 * same {@see ScheduleFields::schema()} the standalone pages render, creating a
 * schedule against the report being edited. There is deliberately no separate
 * header action on the page — two buttons opening the same modal would drift the
 * first time one of them grew an argument.
 */
class SchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'schedules';

    protected static ?string $title = 'Schedules';

    public function form(Schema $schema): Schema
    {
        // The report is fixed by the relationship, so no picker.
        return $schema->schema(ScheduleFields::schema(includeReportPicker: false));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->emptyStateHeading(__('export-scheduler::scheduler.no_schedules'))
            ->emptyStateDescription(__('export-scheduler::scheduler.no_schedules_description'))
            ->columns([
                TextColumn::make('schedule_frequency')
                    ->label(__('export-scheduler::scheduler.schedule_frequency'))
                    ->badge(),
                TextColumn::make('recipient')
                    ->label(__('export-scheduler::scheduler.recipient'))
                    ->state(function (ScheduledReport $record) {
                        if (! $record->recipient) {
                            return null;
                        }

                        $label = app(ResolvesReportUsers::class)->getLabel($record->recipient);

                        return $record->cc_count
                            ? $label . ' +' . $record->cc_count
                            : $label;
                    }),
                TextColumn::make('next_run_at')
                    ->label(__('export-scheduler::scheduler.next_run_at'))
                    ->dateTime(),
                TextColumn::make('last_run_at')
                    ->label(__('export-scheduler::scheduler.last_run'))
                    ->dateTime(),
                ToggleColumn::make('enabled')
                    ->label(__('export-scheduler::scheduler.enabled')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('export-scheduler::scheduler.schedule_action'))
                    ->modalWidth(static::modalWidth()),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth(static::modalWidth()),
                DeleteAction::make()
                    ->modalWidth(static::modalWidth()),
            ]);
    }

    /**
     * The schedule form is wide — frequency, recipient, cc and overrides — so the
     * modal size is worth configuring rather than hardcoding.
     */
    protected static function modalWidth(): Width | string
    {
        return config('export-scheduler.navigation.schedules.modal_width') ?? Width::FiveExtraLarge;
    }

    /**
     * Scheduling is an owner-only concern, so the whole panel is absent for
     * someone who merely has the report shared with them.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->isOwnedBy(auth()->user());
    }
}
