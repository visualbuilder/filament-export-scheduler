<?php

namespace VisualBuilder\ExportScheduler\Jobs;

use AnourValar\EloquentSerialize\Facades\EloquentSerializeFacade;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use League\Csv\Writer;
use SplTempFileObject;
use Throwable;
use  Filament\Actions\Exports\Jobs\ExportCsv as BaseExportCsv;

class ExportCsv extends BaseExportCsv
{
    public function handle(): void
    {
        auth()->setUser($this->export->user);

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
                $exceptions[$exception::class] = $exception;
            }

            $processedRows++;
        }

        $filePath = $this->export->getFileDirectory() . DIRECTORY_SEPARATOR . str_pad(strval($this->page), 16, '0', STR_PAD_LEFT) . '.csv';
        $this->export->getFileDisk()->put($filePath, $csv->toString(), Filesystem::VISIBILITY_PRIVATE);

        $this->export::query()
            ->whereKey($this->export->getKey())
            ->update([
                'processed_rows' => DB::raw('processed_rows + ' . $processedRows),
                'successful_rows' => DB::raw('successful_rows + ' . $successfulRows),
            ]);

        $this->export::query()
            ->whereKey($this->export->getKey())
            ->whereColumn('processed_rows', '>', 'total_rows')
            ->update([
                'processed_rows' => DB::raw('total_rows'),
            ]);

        $this->export::query()
            ->whereKey($this->export->getKey())
            ->whereColumn('successful_rows', '>', 'total_rows')
            ->update([
                'successful_rows' => DB::raw('total_rows'),
            ]);

        $this->handleExceptions($exceptions ?? []);
    }
}
