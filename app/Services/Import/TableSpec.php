<?php

namespace App\Services\Import;

/**
 * Declarative description of one importable table.
 * Adding a table = one new entry in TableSpecs::all().
 */
final readonly class TableSpec
{
    /**
     * @param string   $table        DB table name
     * @param string   $csvFile      Filename inside the import directory
     * @param string[] $columns      Columns present in the CSV
     * @param string[] $requiredCols Columns that must be non-null per row
     * @param string[] $uniqueBy     Columns that identify a row for upsert
     */
    public function __construct(
        public string $table,
        public string $csvFile,
        public array $columns,
        public array $requiredCols = ['id'],
        public array $uniqueBy = ['id'],
    ) {}
}
