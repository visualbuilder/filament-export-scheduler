<?php

namespace Visualbuilder\ExportScheduler\Jobs;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use League\Csv\Writer;
use SplTempFileObject;
use Throwable;

class ExportSqlQuery implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use Queueable;
    use SerializesModels;

    public function __construct(
        protected Export $export,
        protected string $sql,
    ) {}

    public function handle(): void
    {
        $results = DB::select($this->sql);

        if (empty($results)) {
            DB::transaction(function (): void {
                $this->export::query()
                    ->whereKey($this->export->getKey())
                    ->lockForUpdate()
                    ->update([
                        'processed_rows' => 0,
                        'successful_rows' => 0,
                        'total_rows' => 0,
                    ]);
            });

            return;
        }

        // Create separate headers CSV file
        $headers = array_keys((array) $results[0]);
        $headersCsv = Writer::createFromFileObject(new SplTempFileObject);
        $headersCsv->insertOne($headers);
        $headersPath = $this->export->getFileDirectory() . DIRECTORY_SEPARATOR . 'headers.csv';

        // Create data CSV file
        $csv = Writer::createFromFileObject(new SplTempFileObject);

        // Write header row from column names
        $csv->insertOne($headers);

        $processedRows = 0;
        $successfulRows = 0;

        foreach ($results as $row) {
            try {
                $csv->insertOne(array_values((array) $row));
                $successfulRows++;
            } catch (Throwable $exception) {
                report($exception);
            }
            $processedRows++;
        }

        $filePath = $this->export->getFileDirectory() . DIRECTORY_SEPARATOR . '0000000000000001.csv';

        DB::transaction(function () use ($csv, $filePath, $headersCsv, $headersPath, $processedRows, $successfulRows): void {
            $this->export::query()
                ->whereKey($this->export->getKey())
                ->lockForUpdate()
                ->update([
                    'processed_rows' => $processedRows,
                    'successful_rows' => $successfulRows,
                    'total_rows' => $processedRows,
                ]);

            // Write headers.csv file
            $this->export->getFileDisk()->put($headersPath, $headersCsv->toString(), Filesystem::VISIBILITY_PRIVATE);

            // Write data CSV file
            $this->export->getFileDisk()->put($filePath, $csv->toString(), Filesystem::VISIBILITY_PRIVATE);
        });
    }
}
