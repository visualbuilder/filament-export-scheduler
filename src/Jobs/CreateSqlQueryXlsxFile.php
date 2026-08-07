<?php

namespace Visualbuilder\ExportScheduler\Jobs;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\File;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use League\Csv\Reader as CsvReader;
use League\Csv\Statement;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Builds the XLSX file for a SQL query report.
 *
 * Filament's CreateXlsxFile resolves an Exporter class from the export in its constructor,
 * which SQL query reports do not have - they store the 'sql_query' sentinel instead. This
 * writes the same file from the CSV pages without one.
 */
class CreateSqlQueryXlsxFile implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(protected Export $export) {}

    public function handle(): void
    {
        $disk = $this->export->getFileDisk();
        $directory = $this->export->getFileDirectory();

        $writer = app(Writer::class);
        $writer->openToFile($temporaryFile = tempnam(sys_get_temp_dir(), $this->export->file_name));

        $writeRowsFromFile = function (string $file) use ($disk, $writer): void {
            $csvReader = CsvReader::from($disk->readStream($file));
            $csvResults = (new Statement)->process($csvReader);

            foreach ($csvResults->getRecords() as $values) {
                $writer->addRow(Row::fromValues($values));
            }
        };

        // Exports written before empty result sets produced headers have no headers.csv.
        if ($disk->exists($headersFile = $directory . DIRECTORY_SEPARATOR . 'headers.csv')) {
            $writeRowsFromFile($headersFile);
        }

        foreach ($disk->files($directory) as $file) {
            if (str($file)->endsWith('headers.csv')) {
                continue;
            }

            if (! str($file)->endsWith('.csv')) {
                continue;
            }

            $writeRowsFromFile($file);
        }

        $writer->close();

        $disk->putFileAs(
            $directory,
            new File($temporaryFile),
            "{$this->export->file_name}.xlsx",
            Filesystem::VISIBILITY_PRIVATE,
        );

        unlink($temporaryFile);
    }
}
