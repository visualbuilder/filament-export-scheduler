<?php

namespace Visualbuilder\ExportScheduler\Support;

use Illuminate\Support\Facades\DB;
use PDO;
use PDOStatement;

/**
 * The result of a SQL query report.
 *
 * Reads the result set through PDO so the selected columns are known even when the query
 * matches nothing - an empty report still needs its headings, both on screen and in the
 * file that gets downloaded.
 */
class SqlQueryResult
{
    /**
     * @param  array<string>  $columns
     * @param  array<array<string, mixed>>  $rows
     */
    public function __construct(
        public readonly array $columns,
        public readonly array $rows,
    ) {}

    public static function run(string $sql): self
    {
        $statement = DB::connection()->getReadPdo()->query($sql);

        $columns = static::readColumnNames($statement);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        // Not every driver can describe a result set, so fall back to the first row's keys.
        if ($columns === []) {
            $columns = array_keys((array) ($rows[0] ?? []));
        }

        return new self($columns, $rows);
    }

    /**
     * @return array<string>
     */
    protected static function readColumnNames(PDOStatement $statement): array
    {
        $columns = [];

        for ($index = 0; $index < $statement->columnCount(); $index++) {
            $name = $statement->getColumnMeta($index)['name'] ?? null;

            if (blank($name)) {
                return [];
            }

            $columns[] = $name;
        }

        return $columns;
    }
}
