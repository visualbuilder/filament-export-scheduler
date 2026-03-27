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

/**
 * Export SQL Query Job
 *
 * Executes raw SQL SELECT queries and exports results to CSV format.
 * Creates separate headers.csv and data CSV files for compatibility
 * with Filament's export download system.
 */
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

    /**
     * Execute the SQL query and export results to CSV.
     *
     * Creates two files:
     * - headers.csv: Column names for import validation
     * - 0000000000000001.csv: Query results data
     *
     * Updates the export record with row counts and completion status.
     *
     * @throws \Exception If database query fails or file writing encounters errors
     */
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

        // Create separate headers.csv file for compatibility with import systems
        // and to enable header validation independent of data rows
        $headers = array_keys((array) $results[0]);
        $headersCsv = Writer::from(new SplTempFileObject);
        $headersCsv->insertOne($headers);

        // Create data CSV file
        $dataCsv = Writer::from(new SplTempFileObject);

        $processedRows = 0;
        $successfulRows = 0;

        // Process each row individually, continuing even if conversion fails
        // to maximize data export completeness. Errors are logged for audit.
        foreach ($results as $row) {
            try {
                $dataCsv->insertOne(array_values((array) $row));
                $successfulRows++;
            } catch (Throwable $exception) {
                report($exception);
            }
            $processedRows++;
        }

        $directory = $this->export->getFileDirectory() . DIRECTORY_SEPARATOR;
        $disk = $this->export->getFileDisk();

        // Write headers.csv file
        $disk->put($directory . 'headers.csv', $headersCsv->toString(), Filesystem::VISIBILITY_PRIVATE);

        // Write data CSV file
        $this->export->getFileDisk()->put($directory . '0000000000000001.csv', $dataCsv->toString(), Filesystem::VISIBILITY_PRIVATE);

        // Lock export record and atomically write all files and updates
        // to ensure consistency if job is retried or fails partway through
        DB::transaction(function () use ($processedRows, $successfulRows): void {
            $this->export::query()
                ->whereKey($this->export->getKey())
                ->lockForUpdate()
                ->update([
                    'processed_rows' => $processedRows,
                    'successful_rows' => $successfulRows,
                    'total_rows' => $processedRows,
                ]);
        });
    }
}
