<?php

namespace VisualBuilder\ExportScheduler\Filament\Forms;

use Closure;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use VisualBuilder\ExportScheduler\Enums\DateRange;
use VisualBuilder\ExportScheduler\Enums\DayOfWeek;
use VisualBuilder\ExportScheduler\Enums\Month;
use VisualBuilder\ExportScheduler\Enums\ScheduleFrequency;
use VisualBuilder\ExportScheduler\Facades\ExportScheduler;
use VisualBuilder\ExportScheduler\Models\ExportSchedule;
use VisualBuilder\ExportScheduler\Traits\InteractsWithExportSchedulerFilter;

class Fields
{
    public static function name(): TextInput
    {
        return TextInput::make('name')
            ->label(__('export-scheduler::scheduler.name'))
            ->placeholder(__('export-scheduler::scheduler.name_placeholder'))
            ->required()
            ->maxLength(191);
    }

    public static function filterReportSection(): Section
    {
        return Section::make('Filter By Associated Records (optional)')
            ->columns()
            ->live()
            ->visible(fn(Get $get) => $get('exporter'))
            ->schema([
                // Section for choosing which relation types to filter by.
                Section::make('Choose the Associated Record Type')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('selected_relations')
                            ->hiddenLabel()
                            ->multiple()
                            ->live()
                            ->preload()
                            ->formatStateUsing(fn(?ExportSchedule $record) => collect($record?->filters ?? [])
                                ->keys()
                                ->reject(fn($key) => $key === 'attributes')
                                ->values()
                                ->all()
                            )
                            ->options(function (Get $get) {
                                $exporter = $get('exporter');
                                if (!$exporter) {
                                    return [];
                                }

                                $exporterReflection = new \ReflectionClass($exporter);
                                $exporterModel = $exporterReflection->getStaticPropertyValue('model');

                                // Filter methods on the exporter model to only include relationships that
                                // - is an instance of a BelongsTo relation
                                // - doesn't require any arguments
                                // - implements the 'InteractsWithExportSchedulerFilter' trait
                                // - is not specifically excluded from the exporter
                                $belongsToRelations = collect((new \ReflectionClass($exporterModel))->getMethods(\ReflectionMethod::IS_PUBLIC))
                                    ->filter(function (\ReflectionMethod $method) use ($exporter, $exporterModel, $exporterReflection) {
                                        $methodReturnType = $method->getReturnType()?->getName();
                                        $methodParamsCount = $method->getNumberOfParameters();

                                        if ($methodReturnType !== BelongsTo::class || $methodParamsCount > 1) {
                                            return false;
                                        }

                                        $relationship = $method->getName();
                                        $exporterTraits = $exporterReflection->getTraitNames();

                                        if (in_array(InteractsWithExportSchedulerFilter::class, $exporterTraits)
                                            && in_array($relationship, $exporter::excludeFilterableRelations())) {
                                            return false;
                                        }

                                        $relationshipInstance = (new $exporterModel)->$relationship();
                                        $relatedModelClass = get_class($relationshipInstance->getRelated());
                                        $relatedModelTraits = (new \ReflectionClass($relatedModelClass))->getTraitNames();

                                        return in_array(InteractsWithExportSchedulerFilter::class, $relatedModelTraits);
                                    })
                                    ->mapWithKeys(function (\ReflectionMethod $method) {
                                        $relationship = $method->getName();

                                        // Convert camelCase to spaced words.
                                        return [
                                            $relationship => ucwords(preg_replace('/(?<!^)([A-Z])/', ' $1', $relationship)),
                                        ];
                                    });

                                return $belongsToRelations->toArray();
                            })
                            ->afterStateUpdated(function ($livewire, $state) {
                                $attributes = $livewire->data['filters']['attributes'] ?? null;

                                // Remove field data from livewire if not selected
                                if (array_key_exists('filters', $livewire->data) && is_array($livewire->data['filters'])) {
                                    $livewire->data['filters'] = array_filter(
                                        $livewire->data['filters'],
                                        fn($filter) => in_array($filter, $state),
                                        ARRAY_FILTER_USE_KEY,
                                    );
                                }

                                if ($attributes) {
                                    $livewire->data['filters']['attributes'] = $attributes;
                                }
                            }),
                    ]),

                // Section that dynamically creates a Select field for each chosen relation.
                Section::make('Select Records for Each Associated Type')
                    ->columnSpan(1)
                    ->visible(fn(Get $get) => $get('selected_relations'))
                    ->live()
                    ->schema(function (Get $get, $livewire) {
                        $exporter = $get('exporter');
                        if (!$exporter) {
                            return [];
                        }

                        // Use the helper method to build each Select field.
                        $selectFields = collect($get('selected_relations') ?? [])
                            ->map(fn($relation) => self::buildRelationSelectField($relation, $exporter))
                            ->toArray();

                        // Add field data to the livewire
                        $livewireData = $livewire->data;
                        if (!array_key_exists('filters', $livewireData) || is_null($livewireData['filters'])) {
                            $livewireData['filters'] = [];
                        }

                        $attributes = array_filter(
                            $livewireData['filters'],
                            fn($key) => $key === 'attributes',
                            ARRAY_FILTER_USE_KEY
                        );
                        $relations = array_filter(
                            $livewireData['filters'],
                            fn($key) => $key !== 'attributes',
                            ARRAY_FILTER_USE_KEY
                        );

                        $livewireData['filters'] = $relations;

                        foreach ($selectFields as $field) {
                            $fieldName = str_replace('filters.', '', $field->getName());
                            if (!array_key_exists($fieldName, $livewireData['filters'])) {
                                $livewireData['filters'][$fieldName] = null;
                            }
                        }

                        $livewireData['filters'] = $livewireData['filters'] + $attributes;
                        $livewire->data = $livewireData;

                        return $selectFields;
                    }),
            ]);
    }

    public static function filterByAttributeSection(): Section
    {
        return Section::make('Filter By Attributes (optional)')
            ->live()
            ->visible(fn(Get $get) => $get('exporter'))
            ->schema(function (Get $get) {
                $exporterClass = $get('exporter');
                $columns = ExportSchedule::getDefaultColumnsForExporter($exporterClass ?? '');

                return [
                    Repeater::make('filters.attributes')
                        ->hiddenLabel()
                        ->reorderable(false)
                        ->columns(3)
                        ->dehydrated(fn($state) => filled($state))
                        ->schema([
                            Group::make()
                                ->columns(3)
                                ->columnSpanFull()
                                ->schema([
                                    Select::make('condition')
                                        ->columnStart(2)
                                        ->options([
                                            'and' => 'AND',
                                            'or' => 'OR'
                                        ])
                                        ->required()
                                        ->hidden(function (Get $get, $component) {
                                            $itemId = str_replace(['data.filters.attributes.', '.condition'], '', $component->getId());

                                            return $itemId === array_key_first($get('../'));
                                        })
                                ]),

                            // field/attribute
                            Select::make('column')
                                ->options(function () use ($columns, $exporterClass) {
                                    $exporterModel = (new \ReflectionClass($exporterClass))->getStaticPropertyValue('model');

                                    return $columns
                                        ->filter(function ($column) use ($exporterModel) {
                                            if (!($name = $column['name'] ?? null)) {
                                                return false;
                                            }

                                            if (!method_exists($exporterModel, $name)) {
                                                return true;
                                            }

                                            return !(new $exporterModel)->{$name}() instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo;
                                        })
                                        ->pluck('label', 'name')
                                        ->toArray();
                                })
                                ->native(false)
                                ->live()
                                ->required()
                                ->afterStateUpdated(function (Set $set) {
                                    $set('value', null);
                                    $set('operator', null);
                                }),

                            Group::make()
                                ->columnSpan(2)
                                ->columns()
                                ->visible(fn(Get $get) => $columns->contains(fn($item) => $item['name'] === $get('column')))
                                ->schema([
                                    // operator
                                    Select::make('operator')
                                        ->required()
                                        ->native(false)
                                        ->options(function (Get $get) use ($columns, $exporterClass) {
                                            $column = $get('column');
                                            $exporterModel = (new \ReflectionClass($exporterClass))->getStaticPropertyValue('model');
                                            $casts = (new $exporterModel)->getCasts();
                                            $type = $casts[$column] ?? null;

                                            return match (true) {
                                                // enum
                                                filled(Helper::extractEnumCast($column, $exporterModel)) => [
                                                    'in' => 'is present in',
                                                    'not_in' => 'is not present in'
                                                ],

                                                // date/datetime/timestamp
                                                Helper::isDateTimeCast($column, $type) => ['<>' => 'is from', 'since' => 'since'],

                                                // boolean
                                                Helper::isBooleanCast($type) => [
                                                    '=' => 'is',
                                                    '!=' => 'is not'
                                                ],

                                                default => [
                                                    '=' => 'is equal to',
                                                    '!=' => 'is not equal to',
                                                    'like' => 'is like',
                                                ]
                                            };
                                        })
                                        ->afterStateUpdated(function ($state, Set $set) {
                                            if ($state === 'since') {
                                                $set('value', ['amount' => 1, 'unit' => 'days']);
                                            } else {
                                                $set('value', null);
                                            }
                                        }),

                                    // value
                                    Group::make()
                                        ->visible(function(Get $get) use ($exporterClass) {return $exporterClass&& $get('operator'); })
                                        ->schema(function (Get $get) use ($exporterClass) {
                                            if(!$exporterClass)
                                                return [];
                                            $column = $get('column');
                                            $exporterModel = (new \ReflectionClass($exporterClass))->getStaticPropertyValue('model');
                                            $casts = (new $exporterModel)->getCasts();
                                            $type = $casts[$column] ?? null;
                                            $key = 'value';
                                            $enumCast = Helper::extractEnumCast($column, $exporterModel);

                                            $operator = $get('operator');
                                            $field = match (true) {
                                                // enum
                                                filled($enumCast) => Select::make($key)
                                                    ->multiple()
                                                    ->selectablePlaceholder(false)
                                                    ->native(false)
                                                    ->options(fn() => collect($enumCast::cases())
                                                        ->mapWithKeys(fn($case) => [$case->value => $case->getLabel()])
                                                    ),

                                                // date/datetime/timestamp
                                                Helper::isDateTimeCast($column, $type) => $operator === 'since'
                                                    ? self::dateSince($key)
                                                    : self::dateRange($key),

                                                // boolean
                                                Helper::isBooleanCast($type) => Toggle::make($key),

                                                // others
                                                default => TextInput::make($key)
                                            };

                                            if (method_exists($field, 'required')) {
                                                $field = $field->required();
                                            }

                                            return [$field];
                                        })
                                ])
                        ])
                        ->addActionLabel('Add filter')
                ];
            });
    }

    /**
     * Helper method that builds a dynamic Select field for a given relation.
     */
    protected static function buildRelationSelectField(string $relation, $exporter): Select
    {
        // Get the exporter model via reflection.
        $exporterModel = (new \ReflectionClass($exporter))->getStaticPropertyValue('model');
        // Instantiate and get the relationship instance.
        $relationshipInstance = (new $exporterModel)->$relation();
        // Determine the related model's class.
        $relatedModelClass = get_class($relationshipInstance->getRelated());
        // Get the filter label for the related model's class;
        $filterLabel = (new $relatedModelClass)->getFilterLabel();

        return Select::make("filters.$relation")
            ->label(ucwords(preg_replace('/(?<!^)([A-Z])/', ' $1', $relation)))
            ->live()
            ->preload()
            ->multiple()
            ->searchable()
            ->getSearchResultsUsing(function (string $search) use ($filterLabel, $relatedModelClass): array {
                return $relatedModelClass::where($filterLabel, 'like', "%{$search}%")
                    ->limit(50)
                    ->pluck($filterLabel, 'id')
                    ->toArray();
            })
            ->getOptionLabelsUsing(function (array $values) use ($filterLabel, $relatedModelClass): array {
                return $relatedModelClass::whereIn('id', $values)
                    ->pluck($filterLabel, 'id')
                    ->toArray();
            });
    }

    public static function exporter(): Select
    {
        return Select::make('exporter')
            ->label(__('export-scheduler::scheduler.exporter'))
            ->hintIcon('heroicon-m-question-mark-circle', tooltip: __('export-scheduler::scheduler.exporter_hint'))
            ->hintColor('info')
            ->options(ExportScheduler::listExporters())
            ->native(false)
            ->live()
            ->required()
            ->afterStateUpdated(function (?ExportSchedule $record, $state, Set $set, $livewire) {
                /** Clear any existing selected_relations & filter data */
                if (array_key_exists('filters', $livewire->data)) {
                    $livewire->data['filters'] = [];
                    $set('selected_relations', null);
                }

                /** Update the column definitions when changing exporter */
                $defaultColumns = ExportSchedule::getDefaultColumnsForExporter($state ?? '');

                $set('columns', $defaultColumns->toArray() ?? []);
                $set('available_columns', []);

                if ($record) {
                    $record->update([
                        'exporter' => $state,
                        'columns' => $defaultColumns ?? [],
                    ]);
                }

            });
    }

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

    public static function customCronExpression(): TextInput
    {
        return TextInput::make('custom_cron_expression')
            ->label(__('export-scheduler::scheduler.custom_cron_expression'))
            ->visible(fn(Get $get) => ScheduleFrequency::CRON->is($get('schedule_frequency')))
            ->required(fn(Get $get) => ScheduleFrequency::CRON->is($get('schedule_frequency')))
            ->hintColor('info')
            ->placeholder('eg 0 0 * * 0 ')
            ->rules([
                fn(): Closure => function (string $attribute, $value, Closure $fail) {
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
            ->content(new HtmlString("<div style='line-height: 1.7'><p>" . __('export-scheduler::scheduler.cron_expression_hint') . '</p></div>'));
    }

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

    public static function scheduleTimeZone(): Select
    {
        return Select::make('schedule_timezone')
            ->label(__('export-scheduler::scheduler.schedule_timezone'))
            ->options(timezone_identifiers_list())
            ->searchable()
            ->native(false)
            ->default(config('app.timezone'));
    }

    public static function scheduleTime(): TimePicker
    {
        return TimePicker::make('schedule_time')
            ->seconds(false)
            ->label(__('export-scheduler::scheduler.schedule_time'))
            ->visible(fn(Get $get) => $get('schedule_frequency') !== ScheduleFrequency::CRON->value)
            ->required(fn(Get $get) => $get('schedule_frequency') !== ScheduleFrequency::CRON->value)
            ->default('00:00');
    }

    public static function dateRange($key = 'date_range'): Select
    {
        return Select::make($key)
            ->placeholder(__('export-scheduler::scheduler.date_range_placeholder'))
            ->hintIcon('heroicon-m-question-mark-circle', tooltip: __('export-scheduler::scheduler.date_range_tooltip'))
            ->hintColor('info')
            ->label(__('export-scheduler::scheduler.date_range'))
            ->options(DateRange::selectArray())
            ->native(false);
    }

    public static function dateSince($key = 'since'): Group
    {
        return Group::make()
            ->statePath($key)
            ->columns(2)
            ->schema([
                TextInput::make('amount')
                    ->numeric()
                    ->default(1)
                    ->required(),
                Select::make('unit')
                    ->options([
                        'days' => __('export-scheduler::scheduler.days'),
                        'weeks' => __('export-scheduler::scheduler.weeks'),
                        'months' => __('export-scheduler::scheduler.months'),
                        'years' => __('export-scheduler::scheduler.years'),
                    ])
                    ->native(false)
                    ->required(),
            ]);
    }

    public static function formats(): Select
    {
        return Select::make('formats')
            ->label(__('export-scheduler::scheduler.formats'))
            ->options([
                'csv' => __('CSV'),
                'xlsx' => __('XLSX'),
            ])
            ->default([ExportFormat::Xlsx])
            ->native(false)
            ->multiple()
            ->required();
    }

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
                $allColumns = ExportSchedule::getDefaultColumnsForExporter($get('exporter') ?? '');
                $currentColumnNames = collect($state)->pluck('name')->all();
                $newAvailableColumns = $allColumns->reject(function ($column) use ($currentColumnNames) {
                    return in_array($column['name'], $currentColumnNames);
                });
                $set('available_columns', $newAvailableColumns->values()->all());

            })
            ->live()
            ->itemLabel(fn(array $state): ?string => $state['label'] ?? null)
            ->maxItems(fn(Get $get) => $get('exporter') ? ExportSchedule::getDefaultColumnsForExporter($get('exporter'))->count() : 0)
            ->default(fn(Get $get) => ExportSchedule::getDefaultColumnsForExporter($get('exporter') ?? '')->toArray())
            ->schema([
                Hidden::make('name'),
                TextInput::make('label')->hiddenLabel(),
            ]);
    }

    public static function availableColumns(): Repeater
    {
        return Repeater::make('available_columns')
            ->schema([
                Hidden::make('name'),
                TextInput::make('label')->hiddenLabel(),
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
                        $allColumns = ExportSchedule::getDefaultColumnsForExporter($get('exporter') ?? '');
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
                            $deletedItemKey = (string)Str::uuid(); // Generate a unique key
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
                $allColumns = ExportSchedule::getDefaultColumnsForExporter($get('exporter') ?? '');
                $currentColumnNames = collect($get('columns'))->pluck('name')->all();
                $availableColumns = $allColumns->reject(function ($column) use ($currentColumnNames) {
                    return in_array($column['name'], $currentColumnNames);
                });

                return $availableColumns->toArray();
            });
    }

    public static function enableToggle(): Toggle
    {
        return Toggle::make('enabled')
            ->inline(false)
            ->label(__('export-scheduler::scheduler.enabled'));
    }

    public static function copyToUser(): Fieldset
    {
        return Fieldset::make(__('export-scheduler::scheduler.cc'))
            ->schema(self::copyToUserFields())
            ->visible(fn(Get $get) => $get('owner_id'));
    }

    public static function copyToUserFields(): array
    {
        return [
            Placeholder::make('Data Security Warning')
                ->content(fn(Get $get) => new HtmlString(__('export-scheduler::scheduler.cc_warning', ['owner_type' => class_basename($get('owner_type'))]))),

            Repeater::make('cc')
                ->label('')
                ->addActionLabel(__('export-scheduler::scheduler.cc_add_label'))
                ->simple(self::selectCopyToUser())
        ];
    }

    public static function selectCopyToUser(): Select
    {
        return Select::make('id')
            ->label('User')
            ->placeholder(__('export-scheduler::scheduler.cc_placeholder'))
            ->searchable()
            ->options(function ($get) {
                $type = $get('../../owner_type');
                $ownerId = $get('../../owner_id');
                $ccItems = $get('../../cc');
                $ccIds = [];
                if (is_array($ccItems)) {
                    $ccIds = collect($ccItems)->pluck('id')->toArray();
                }
                $excludeIds = array_filter(array_merge($ccIds, [$ownerId]));

                return $type ? $type::query()->whereNotIn('id', $excludeIds)->pluck('email', 'id') : [];
            })
            ->getOptionLabelUsing(function ($value, Get $get) {
                $type = $get('../../owner_type');

                return $type ? $type::query()->find($value)?->email : '';
            });
    }

    public static function ownerMorphSelect(string $fieldName = 'owner', bool $native = false, bool $searchable = true): MorphToSelect
    {
        $types = [];
        $userModels = config('export-scheduler.user_models', []);

        foreach ($userModels as $userModel) {
            if (is_array($userModel) && isset($userModel['model'], $userModel['title_attribute'])) {
                $types[] = MorphToSelect\Type::make($userModel['model'])
                    ->titleAttribute($userModel['title_attribute']);
            }
        }

        return MorphToSelect::make($fieldName)
            ->label(__('export-scheduler::scheduler.owner'))
            ->types($types)
            ->native($native)
            ->required()
            ->live()
            ->searchable($searchable);
    }
}
