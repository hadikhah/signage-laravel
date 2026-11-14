<?php

namespace App\Services\Import;

use Illuminate\Support\Collection;

/**
 * Per-run counters and options. Mutable by design — passed by reference
 * to each TableImporter::import() call so totals aggregate naturally.
 */
final class ImportContext
{
    public int $inserted = 0;

    public int $updated = 0;

    public int $skipped = 0;

    /** @var Collection<int, array{table: string, rowNumber: int, message: string}> */
    public Collection $orphans;

    /** @var Collection<int, array{table: string, rowNumber: int, message: string}> */
    public Collection $errors;

    /** @var array<string, array{inserted: int, updated: int, skipped: int}> */
    public array $perTable = [];

    public function __construct(
        public readonly bool $dryRun,
        public readonly bool $truncate,
    ) {
        $this->orphans = collect();
        $this->errors = collect();
    }

    public function record(string $table, int $inserted, int $updated, int $skipped): void
    {
        $this->perTable[$table] = compact('inserted', 'updated', 'skipped');
        $this->inserted += $inserted;
        $this->updated += $updated;
        $this->skipped += $skipped;
    }

    public function hasErrors(): bool
    {
        return $this->errors->isNotEmpty();
    }
}
