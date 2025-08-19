<?php

namespace Visualbuilder\ExportScheduler\Jobs;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use League\Csv\Writer;
use SplTempFileObject;
use Throwable;
use Filament\Actions\Exports\Jobs\ExportCsv as BaseExportCsv;

class ExportCsv extends BaseExportCsv
{
    public function handle(): void
    {
        /** @var Authenticatable $user */
        $user = $this->export->user;

        auth()->setUser($user);

        $processedRows = 0;
        $successfulRows = 0;

        $csv = Writer::createFromFileObject(new SplTempFileObject);
        $csv->setDelimiter($this->exporter::getCsvDelimiter());

        $query = EloquentSerializeFacade::unserialize($this->query);

        foreach ($this->exporter->getCachedColumns() as $column) {
            $column->applyRelationshipAggregates($query);
            $column->applyEagerLoading($query);
        }

        foreach ($query->find($this->records) as $record) {
            try {
                $csv->insertOne(($this->exporter)($record));

                $successfulRows++;
            } catch (Throwable $exception) {
                report($exception);
            }

            $processedRows++;
        }

        $filePath = $this->export->getFileDirectory() . DIRECTORY_SEPARATOR . str_pad(strval($this->page), 16, '0', STR_PAD_LEFT) . '.csv';

        DB::transaction(function () use ($csv, $filePath, $processedRows, $successfulRows): void {
            $this->export::query()
                ->whereKey($this->export->getKey())
                ->lockForUpdate()
                ->update([
                    'processed_rows' => new Expression('processed_rows + ' . $processedRows),
                    'successful_rows' => new Expression('successful_rows + ' . $successfulRows),
                ]);

            $this->export::query()
                ->whereKey($this->export->getKey())
                ->whereColumn('processed_rows', '>', 'total_rows')
                ->lockForUpdate()
                ->update([
                    'processed_rows' => new Expression('total_rows'),
                ]);

            $this->export::query()
                ->whereKey($this->export->getKey())
                ->whereColumn('successful_rows', '>', 'total_rows')
                ->lockForUpdate()
                ->update([
                    'successful_rows' => new Expression('total_rows'),
                ]);

            $this->export->getFileDisk()->put($filePath, $csv->toString(), Filesystem::VISIBILITY_PRIVATE);
        });
    }
}
