<?php

namespace Visualbuilder\ExportScheduler\Filament\Forms;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Visualbuilder\ExportScheduler\Enums\DateRange;
use Visualbuilder\ExportScheduler\Enums\DayOfWeek;
use Visualbuilder\ExportScheduler\Enums\Month;
use Visualbuilder\ExportScheduler\Enums\ReportType;
use Visualbuilder\ExportScheduler\Enums\ScheduleFrequency;
use Visualbuilder\ExportScheduler\Facades\ExportScheduler;
use Visualbuilder\ExportScheduler\Models\CustomReport;
use Visualbuilder\ExportScheduler\Traits\InteractsWithExportSchedulerFilter;

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

    public static function canUseSqlQuery(): bool
    {
        $roles = config('export-scheduler.sql_query_roles', []);

        if (empty($roles)) {
            return true;
        }

        $user = auth()->user();

        return $user && method_exists($user, 'hasRole') && $user->hasRole($roles);
    }

    public static function reportType(): Select
    {
        return Select::make('report_type')
            ->label('Report Type')
            ->options(fn () => self::canUseSqlQuery()
                ? ReportType::class
                : [ReportType::EXPORTER->value => ReportType::EXPORTER->getLabel()])
            ->default(ReportType::EXPORTER->value)
            ->required()
            ->native(false)
            ->live()
            ->visible(fn (?CustomReport $record) => self::canUseSqlQuery() || $record?->isSqlQuery())
            ->afterStateUpdated(function (Set $set, $state) {
                if ($state === ReportType::SQL_QUERY->value) {
                    $set('exporter', null);
                    $set('columns', []);
                    $set('available_columns', []);
                } else {
                    $set('sql_query', null);
                }
            });
    }

    public static function sqlQuery(): Textarea
    {
        return Textarea::make('sql_query')
            ->label('SQL Query')
            ->placeholder("SELECT column1, column2\nFROM table_name\nWHERE condition\nORDER BY column1")
            ->rows(8)
            ->required(fn (Get $get) => $get('report_type') === ReportType::SQL_QUERY->value)
            ->visible(fn (Get $get) => $get('report_type') === ReportType::SQL_QUERY->value)
            ->rules([
                fn (): Closure => function (string $attribute, $value, Closure $fail) {
                    if (blank($value)) {
                        return;
                    }
                    $errors = CustomReport::validateSqlQuery($value);
                    foreach ($errors as $error) {
                        $fail($error);
                    }
                },
            ]);
    }

    public static function filterReportSection(): Section
    {
        return Section::make('Filter By Associated Records (optional)')
            ->columns()
            ->live()
            ->visible(fn (Get $get) => $get('exporter') && ($get('report_type') ?? ReportType::EXPORTER->value) !== ReportType::SQL_QUERY->value)
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
                            ->formatStateUsing(
                                fn (?CustomReport $record) => collect($record?->filters ?? [])
                                    ->keys()
                                    ->reject(fn ($key) => $key === 'attributes')
                                    ->values()
                                    ->all()
                            )
                            ->options(function (Get $get) {
                                $exporter = $get('exporter');
                                if (! $exporter) {
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
                                        fn ($filter) => in_array($filter, $state),
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
                    ->visible(fn (Get $get) => $get('selected_relations'))
                    ->live()
                    ->schema(function (Get $get, $livewire) {
                        $exporter = $get('exporter');
                        if (! $exporter) {
                            return [];
                        }

                        // Use the helper method to build each Select field.
                        $selectFields = collect($get('selected_relations') ?? [])
                            ->map(fn ($relation) => self::buildRelationSelectField($relation, $exporter))
                            ->toArray();

                        // Add field data to the livewire
                        $livewireData = $livewire->data;
                        if (! array_key_exists('filters', $livewireData) || is_null($livewireData['filters'])) {
                            $livewireData['filters'] = [];
                        }

                        $attributes = array_filter(
                            $livewireData['filters'],
                            fn ($key) => $key === 'attributes',
                            ARRAY_FILTER_USE_KEY
                        );
                        $relations = array_filter(
                            $livewireData['filters'],
                            fn ($key) => $key !== 'attributes',
                            ARRAY_FILTER_USE_KEY
                        );

                        $livewireData['filters'] = $relations;

                        foreach ($selectFields as $field) {
                            $fieldName = str_replace('filters.', '', $field->getName());
                            if (! array_key_exists($fieldName, $livewireData['filters'])) {
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
            ->visible(fn (Get $get) => $get('exporter') && ($get('report_type') ?? ReportType::EXPORTER->value) !== ReportType::SQL_QUERY->value)
            ->schema(function (Get $get) {
                $exporterClass = $get('exporter');
                $columns = CustomReport::getDefaultColumnsForExporter($exporterClass ?? '')
                    ->reject(function ($column) use ($exporterClass) {
                        $excludedMethod = 'excludeFilterableAttributes';
                        $columnName = $column['name'] ?? null;

                        return filled($columnName)
                            && method_exists($exporterClass, $excludedMethod)
                            && in_array($columnName, $exporterClass::$excludedMethod());
                    });

                return [
                    Repeater::make('filters.attributes')
                        ->hiddenLabel()
                        ->reorderable(false)
                        ->columns(3)
                        ->dehydrated(fn ($state) => filled($state))
                        ->schema([
                            Group::make()
                                ->columns(3)
                                ->columnSpanFull()
                                ->schema([
                                    Select::make('condition')
                                        ->columnStart(2)
                                        ->options([
                                            'and' => 'AND',
                                            'or' => 'OR',
                                        ])
                                        ->required()
                                        ->hidden(function (Get $get, $component) {
                                            $itemId = str_replace(['data.filters.attributes.', '.condition'], '', $component->getId());

                                            return $itemId === array_key_first($get('../'));
                                        }),
                                ]),

                            // field/attribute
                            Select::make('column')
                                ->options(function () use ($columns, $exporterClass) {
                                    $exporterModel = (new \ReflectionClass($exporterClass))->getStaticPropertyValue('model');

                                    return $columns
                                        ->filter(function ($column) use ($exporterModel) {
                                            if (! ($name = $column['name'] ?? null)) {
                                                return false;
                                            }

                                            if (! method_exists($exporterModel, $name)) {
                                                return true;
                                            }

                                            return ! (new $exporterModel)->{$name}() instanceof BelongsTo;
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
                                ->visible(fn (Get $get) => $columns->contains(fn ($item) => $item['name'] === $get('column')))
                                ->schema([
                                    // operator
                                    Select::make('operator')
                                        ->required()
                                        ->native(false)
                                        ->options(function (Get $get) use ($exporterClass) {
                                            $column = $get('column');
                                            $exporterModel = (new \ReflectionClass($exporterClass))->getStaticPropertyValue('model');
                                            $type = Helper::extractCastType($column, $exporterModel);

                                            return match (true) {
                                                // enum
                                                filled(Helper::extractEnumCast($column, $exporterModel)) => [
                                                    'in' => 'is present in',
                                                    'not_in' => 'is not present in',
                                                ],

                                                // date/datetime/timestamp
                                                Helper::isDateTimeCast($column, $type) => [
                                                    '<>' => 'is from',
                                                    'since' => 'since',
                                                    'before' => 'before (next)',
                                                    'is_in' => 'is in',
                                                    'is_before' => 'is before',
                                                    'is_after' => 'is after',
                                                ],

                                                // boolean
                                                Helper::isBooleanCast($type) => [
                                                    '=' => 'is',
                                                    '!=' => 'is not',
                                                ],

                                                default => [
                                                    '=' => 'is equal to',
                                                    '!=' => 'is not equal to',
                                                    'like' => 'is like',
                                                ]
                                            };
                                        }),

                                    // value
                                    Group::make()
                                        ->visible(fn (Get $get) => $exporterClass && $get('operator'))
                                        ->schema(function (Get $get) use ($exporterClass) {
                                            if (! $exporterClass) {
                                                return [];
                                            }

                                            $column = $get('column');
                                            $exporterModel = (new \ReflectionClass($exporterClass))->getStaticPropertyValue('model');
                                            $type = Helper::extractCastType($column, $exporterModel);
                                            $key = 'value';
                                            $enumCast = Helper::extractEnumCast($column, $exporterModel);

                                            $operator = $get('operator');
                                            $field = match (true) {
                                                // enum
                                                filled($enumCast) => Select::make($key)
                                                    ->multiple()
                                                    ->selectablePlaceholder(false)
                                                    ->native(false)
                                                    ->options(
                                                        fn () => collect($enumCast::cases())
                                                            ->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
                                                    ),

                                                // date/datetime/timestamp
                                                Helper::isDateTimeCast($column, $type) => match ($operator) {
                                                    'since' => self::dateSince(),
                                                    'before' => self::dateBefore(),
                                                    '<>' => self::dateRange($key),
                                                    'is_in' => self::futureDateRange($key),
                                                    'is_before', 'is_after' => DatePicker::make($key),
                                                    default => self::dateRange($key),
                                                },

                                                // boolean
                                                Helper::isBooleanCast($type) => Toggle::make($key),

                                                // others
                                                default => TextInput::make($key)
                                            };

                                            if (method_exists($field, 'required')) {
                                                $field = $field->required();
                                            }

                                            return [$field];
                                        }),
                                ]),
                        ])
                        ->addActionLabel('Add filter'),
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
            ->options(ExportScheduler::listExporters())
            ->searchable()
            ->native(false)
            ->live()
            ->visible(fn (Get $get) => ($get('report_type') ?? ReportType::EXPORTER->value) !== ReportType::SQL_QUERY->value)
            ->required(fn (Get $get) => ($get('report_type') ?? ReportType::EXPORTER->value) !== ReportType::SQL_QUERY->value)
            ->afterStateUpdated(function (?CustomReport $record, $state, Set $set, $livewire) {
                /** Clear any existing selected_relations & filter data */
                if (array_key_exists('filters', $livewire->data)) {
                    $livewire->data['filters'] = [];
                    $set('selected_relations', null);
                }

                /** Update the column definitions when changing exporter */
                $defaultColumns = CustomReport::getDefaultColumnsForExporter($state ?? '');

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
            ->options(ScheduleFrequency::selectArray())
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
        return TextInput::make('cron')
            ->label(__('export-scheduler::scheduler.custom_cron_expression'))
            ->visible(fn (Get $get) => ScheduleFrequency::CRON->is($get('schedule_frequency')))
            ->required(fn (Get $get) => ScheduleFrequency::CRON->is($get('schedule_frequency')))
            ->hintColor('info')
            ->placeholder('eg 0 0 * * 0 ')
            ->rules([
                fn (): Closure => function (string $attribute, $value, Closure $fail) {
                    if (! ExportScheduler::isValidCronExpression($value)) {
                        $fail(__('Invalid cron expression'));
                    }
                },
            ]);
    }

    public static function cronHint(): TextEntry
    {
        return TextEntry::make(__('Cron Tips'))
            ->visible(fn (Get $get) => ScheduleFrequency::CRON->is($get('schedule_frequency')))
            ->belowContent(new HtmlString("<div style='line-height: 1.7'><p>" . __('export-scheduler::scheduler.cron_expression_hint') . '</p></div>'));
    }

    public static function scheduleDayOfWeek(): Select
    {
        return Select::make('schedule_day_of_week')
            ->label(__('export-scheduler::scheduler.schedule_day_of_week'))
            ->placeholder(__('export-scheduler::scheduler.schedule_day_of_week_placeholder'))
            ->options(DayOfWeek::class)
            ->native(false)
            ->nullable()
            ->searchable()
            ->visible(fn (Get $get) => $get('schedule_frequency') === ScheduleFrequency::WEEKLY->value)
            ->required(fn (Get $get) => $get('schedule_frequency') === ScheduleFrequency::WEEKLY->value);
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
            ->searchable()
            ->visible(fn (Get $get) => Helper::isDayOfMonthFieldRequired($get))
            ->required(fn (Get $get) => Helper::isDayOfMonthFieldRequired($get));
    }

    public static function scheduleMonth(): Select
    {
        return Select::make('schedule_month')
            ->label(__('export-scheduler::scheduler.schedule_month'))
            ->placeholder(__('export-scheduler::scheduler.schedule_month_placeholder'))
            ->options(Month::class)
            ->native(false)
            ->nullable()
            ->searchable()
            ->visible(fn (Get $get) => $get('schedule_frequency') === ScheduleFrequency::YEARLY->value)
            ->required(fn (Get $get) => $get('schedule_frequency') === ScheduleFrequency::YEARLY->value);
    }

    public static function scheduleStartMonth(): Select
    {
        return Select::make('schedule_start_month')
            ->label(__('export-scheduler::scheduler.schedule_start_month'))
            ->placeholder(__('export-scheduler::scheduler.schedule_start_month_placeholder'))
            ->options(Month::class)
            ->native(false)
            ->nullable()
            ->searchable()
            ->visible(fn (Get $get) => Helper::isStartDateRequired($get))
            ->required(fn (Get $get) => Helper::isStartDateRequired($get));
    }

    public static function scheduleTimeZone(): Select
    {
        return Select::make('schedule_timezone')
            ->label(__('export-scheduler::scheduler.schedule_timezone'))
            ->options(fn () => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
            ->searchable()
            ->native(false)
            ->required()
            ->default(config('app.timezone'));
    }

    public static function scheduleTime(): TimePicker
    {
        return TimePicker::make('schedule_time')
            ->seconds(false)
            ->label(__('export-scheduler::scheduler.schedule_time'))
            ->visible(fn (Get $get) => $get('schedule_frequency') !== ScheduleFrequency::CRON->value)
            ->required(fn (Get $get) => $get('schedule_frequency') !== ScheduleFrequency::CRON->value)
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
            ->searchable()
            ->native(false);
    }

    public static function dateSince(): Group
    {
        return Group::make()
            ->columns(2)
            ->schema([
                TextInput::make('amount')
                    ->numeric()
                    ->default(1),
                Select::make('unit')
                    ->options([
                        'days' => __('export-scheduler::scheduler.days'),
                        'weeks' => __('export-scheduler::scheduler.weeks'),
                        'months' => __('export-scheduler::scheduler.months'),
                        'years' => __('export-scheduler::scheduler.years'),
                    ])
                    ->searchable()
                    ->default('days')
                    ->native(false),
            ]);
    }

    public static function dateBefore(): Group
    {
        return Group::make()
            ->columns(2)
            ->schema([
                TextInput::make('amount')
                    ->numeric()
                    ->default(1),
                Select::make('unit')
                    ->options([
                        'days' => __('export-scheduler::scheduler.days'),
                        'weeks' => __('export-scheduler::scheduler.weeks'),
                        'months' => __('export-scheduler::scheduler.months'),
                        'years' => __('export-scheduler::scheduler.years'),
                    ])
                    ->searchable()
                    ->default('days')
                    ->native(false),
            ]);
    }

    public static function futureDateRange($key = 'value'): Select
    {
        return Select::make($key)
            ->placeholder(__('export-scheduler::scheduler.date_range_placeholder'))
            ->hintIcon('heroicon-m-question-mark-circle', tooltip: __('export-scheduler::scheduler.date_range_tooltip'))
            ->hintColor('info')
            ->label(__('export-scheduler::scheduler.date_range'))
            ->options(DateRange::futurePresets())
            ->searchable()
            ->native(false);
    }

    /**
     * The one file a schedule produces.
     *
     * Stored in the `formats` json column as a single-element array. The column
     * predates this field and once held several formats at a time; keeping the
     * shape avoids a schema change and leaves resolved_formats untouched, so the
     * export pipeline still receives the list it expects.
     */
    public static function format(): Select
    {
        return Select::make('formats')
            ->label(__('export-scheduler::scheduler.format'))
            ->options([
                ExportFormat::Csv->value => __('export-scheduler::scheduler.CSV'),
                ExportFormat::Xlsx->value => __('export-scheduler::scheduler.XLSX'),
            ])
            ->default(ExportFormat::Xlsx->value)
            ->native(false)
            ->required()
            // A legacy row may hold two formats, or none at all when it predates this
            // field being required. Show the first, or the default, rather than an
            // empty required field the user has to guess at.
            ->formatStateUsing(fn ($state) => collect($state)
                ->map(fn ($format) => $format instanceof ExportFormat ? $format->value : (string) $format)
                ->filter()
                ->first() ?? ExportFormat::Xlsx->value)
            ->dehydrateStateUsing(fn ($state) => filled($state) ? [$state] : []);
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
                fn (Action $action) => $action
                    ->label('Remove')
                    ->button(),
            )
            ->afterStateUpdated(function (?CustomReport $record, $state, Get $get, Set $set) {
                $allColumns = CustomReport::getDefaultColumnsForExporter($get('exporter') ?? '');
                $currentColumnNames = collect($state)->pluck('name')->all();
                $newAvailableColumns = $allColumns->reject(function ($column) use ($currentColumnNames) {
                    return in_array($column['name'], $currentColumnNames);
                });
                $set('available_columns', $newAvailableColumns->values()->all());

            })
            ->live()
            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
            ->maxItems(fn (Get $get) => $get('exporter') ? CustomReport::getDefaultColumnsForExporter($get('exporter'))->count() : 0)
            ->default(fn (Get $get) => CustomReport::getDefaultColumnsForExporter($get('exporter') ?? '')->toArray())
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
                        $allColumns = CustomReport::getDefaultColumnsForExporter($get('exporter') ?? '');
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
            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
            ->maxItems(fn (Get $get) => $get('exporter') ? CustomReport::getDefaultColumnsForExporter($get('exporter'))->count() : 0)
            ->addable(false)
            ->formatStateUsing(function (Get $get) {
                $allColumns = CustomReport::getDefaultColumnsForExporter($get('exporter') ?? '');
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
            ->default(true)
            ->label(__('export-scheduler::scheduler.enabled'));
    }

    public static function sendEmptyReport(): Radio
    {
        return Radio::make('send_empty_report')
            ->label(__('export-scheduler::scheduler.send_empty_report'))
            ->inline()
            ->default(true)
            ->required()
            ->boolean(
                trueLabel: __('export-scheduler::scheduler.send_empty_report_true_label'),
                falseLabel: __('export-scheduler::scheduler.send_empty_report_false_label'),
            );
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

    /**
     * Detect if a column path contains a relation to one of the configured user models.
     *
     * @return string|null The relation path up to the user model or null when none found
     */
    protected static function detectUserRelationInColumn(string $model, string $columnPath, array $userModels): ?string
    {
        $segments = explode('.', $columnPath);
        $modelClass = $model;
        $relationParts = [];

        foreach ($segments as $segment) {
            if (! method_exists($modelClass, $segment)) {
                break;
            }

            try {
                $relation = (new $modelClass)->{$segment}();
            } catch (\Throwable $e) {
                break;
            }

            if (! $relation instanceof Relation) {
                break;
            }

            $relationParts[] = $segment;
            $modelClass = get_class($relation->getRelated());

            if (in_array($modelClass, $userModels)) {
                return implode('.', $relationParts);
            }
        }

        return null;
    }

    /**
     * Build a list of user relation options from the exporter's defined columns.
     */
    protected static function collectUserRelationOptions(string $exporter, array $userModels): array
    {
        if (! method_exists($exporter, 'getColumns')) {
            return [];
        }

        $model = $exporter::getModel();
        $options = [];

        foreach ($exporter::getColumns() as $column) {
            if (! method_exists($column, 'getName')) {
                continue;
            }

            $path = self::detectUserRelationInColumn($model, $column->getName(), $userModels);

            if (! $path) {
                continue;
            }

            $segments = explode('.', $path);
            $last = end($segments);
            $label = ucwords(preg_replace('/(?<!^)([A-Z])/', ' $1', str_replace('_', ' ', $last)));

            $options[$path] = $label;
        }

        return $options;
    }

    public static function automaticRecipients(): Section
    {
        return Section::make(__('export-scheduler::scheduler.automatic_recipients'))
            ->columns()
            ->schema([
                TextEntry::make('Send Multiple Reports')
                    ->belowContent('Select a user relationship and the report will be run once for each user found in the data with just their records'),
                Toggle::make('dynamic_owner_enabled')
                    ->inline(false)
                    ->label(__('export-scheduler::scheduler.dynamic_owner_enabled'))
                    ->live(),
                Select::make('dynamic_owner_attribute')
                    ->label(__('export-scheduler::scheduler.dynamic_owner_attribute'))
                    ->visible(fn (Get $get) => $get('dynamic_owner_enabled'))
                    ->options(function (Get $get) {
                        $exporter = $get('exporter');
                        if (! $exporter) {
                            return [];
                        }
                        $userModels = collect(config('export-scheduler.user_models', []))
                            ->map(fn ($m) => is_array($m) ? ($m['model'] ?? null) : $m)
                            ->filter()
                            ->all();

                        return self::collectUserRelationOptions($exporter, $userModels);
                    })
                    ->searchable()
                    ->native(false),
            ]);
    }
}
