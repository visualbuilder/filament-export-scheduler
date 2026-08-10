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

    /**
     * @param  int|null  $limit  stop after this many rows. Only the on-screen viewer
     *                           passes this; an export must never be capped, so the
     *                           default reads the whole result set.
     */
    public static function run(string $sql, ?int $limit = null): self
    {
        $statement = DB::connection()->getReadPdo()->query($sql);

        $columns = static::readColumnNames($statement);
        $rows = $limit === null
            ? $statement->fetchAll(PDO::FETCH_ASSOC)
            : static::fetchUpTo($statement, $limit);

        // Not every driver can describe a result set, so fall back to the first row's keys.
        if ($columns === []) {
            $columns = array_keys((array) ($rows[0] ?? []));
        }

        return new self($columns, $rows);
    }

    /**
     * Read at most $limit rows and abandon the rest.
     *
     * The database still runs the query — an arbitrary author-written statement
     * cannot be safely rewritten with a LIMIT — but PHP never holds more than
     * $limit rows, which is what actually exhausts memory on a large report.
     *
     * @return array<array<string, mixed>>
     */
    protected static function fetchUpTo(PDOStatement $statement, int $limit): array
    {
        $rows = [];

        while (count($rows) < $limit && ($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rows[] = $row;
        }

        $statement->closeCursor();

        return $rows;
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
