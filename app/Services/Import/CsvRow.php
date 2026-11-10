<?php

namespace App\Services\Import;

/**
 * One parsed CSV row. Carries the physical line number so validation
 * errors can say "row 42 of cities.csv".
 */
final readonly class CsvRow
{
    public function __construct(
        public int $lineNumber,
        /** @var array<string, string|null> */
        public array $values,
    ) {}

    public function get(string $column): ?string
    {
        return $this->values[$column] ?? null;
    }

    public function has(string $column): bool
    {
        return array_key_exists($column, $this->values);
    }

    /** @return array<string, string|null> */
    public function only(array $columns): array
    {
        $out = [];
        foreach ($columns as $col) {
            if ($this->has($col)) {
                $out[$col] = $this->values[$col];
            }
        }
        return $out;
    }
}