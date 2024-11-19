<?php

namespace VisualBuilder\ExportScheduler;

use Cron\CronExpression;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

class ExportScheduler
{
    public static function isValidCronExpression(string $expression): bool
    {
        return CronExpression::isValidExpression($expression);
    }

    /**
     * List all exporter classes in the configured directories recursively.
     */
    public function listExporters(): array
    {
        $exporterDirectories = config('export-scheduler.exporter_directories', []);

        $exporters = [];

        foreach ($exporterDirectories as $namespace) {
            // Convert the namespace to a path relative to `app/`
            $path = app_path(str_replace('\\', DIRECTORY_SEPARATOR, Str::after($namespace, 'App\\')));

            if (!is_dir($path)) {
                continue;
            }

            $exporters = array_merge($exporters, $this->getExportersFromDirectory($namespace, $path));
        }

        return $exporters;
    }

    /**
     * Recursively retrieve exporter classes from a given directory.
     */
    protected function getExportersFromDirectory(string $namespace, string $directory): array
    {
        $exporters = [];

        foreach (File::allFiles($directory) as $file) {
            $className = $namespace.'\\'.Str::replaceLast('.php', '', $file->getRelativePathname());
            $className = Str::replace(DIRECTORY_SEPARATOR, '\\', $className);

            // Ensure the class exists and is not abstract
            if (class_exists($className) && !(new ReflectionClass($className))->isAbstract()) {
                // Remove the base namespace but keep subdirectories in the label
                $relativePath = Str::after($className, $namespace.'\\');
                $exporters[$className] = $relativePath;
            }
        }

        return $exporters;
    }
}
