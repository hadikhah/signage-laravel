<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Imports one table from CSV.
 *
 * Responsibilities:
 *   - validate header against the DB schema
 *   - validate required columns per row
 *   - upsert by TableSpec.uniqueBy (idempotent)
 *   - record counts into the context
 *
 * Does NOT: reorder tables, resolve FK targets, reset sequences.
 *
 * ---------------------------------------------------------------------------
 * TRUNCATE — DEV ONLY, DESTRUCTIVE
 * ---------------------------------------------------------------------------
 * When ImportContext.truncate is true, the target table is wiped via
 * TRUNCATE ... RESTART IDENTITY CASCADE BEFORE rows are inserted.
 *
 * Two guards:
 *   1. The calling command must already be in local/testing.
 *   2. This method re-checks app()->environment() so direct instantiation
 *      (tests, future commands) cannot bypass the guard.
 *
 * CASCADE also truncates dependent tables. Never expose this in production.
 * ---------------------------------------------------------------------------
 */
final class TableImporter
{
	public function __construct(private readonly CsvReader $reader) {}

	public function import(TableSpec $spec, string $dir, ImportContext $ctx): void
	{
		$path = "{$dir}/{$spec->csvFile}";
		['header' => $header, 'rows' => $rows] = $this->reader->read($path);

		$this->validateHeader($spec, $path, $header);

		if ($ctx->truncate && ! $ctx->dryRun) {
			$this->truncate($spec);
		}

		$inserted = 0;
		$updated  = 0;

		foreach ($rows as $row) {
			$this->validateRow($spec, $row);
			$payload = $this->normalize($spec, $row);

			$exists = DB::table($spec->table)->where('id', $payload['id'])->exists();

			if ($ctx->dryRun) {
				$exists ? $updated++ : $inserted++;
				continue;
			}

			DB::table($spec->table)->upsert(
				[$payload],
				$spec->uniqueBy,
				$this->updatableColumns($spec),
			);

			$exists ? $updated++ : $inserted++;
		}

		$ctx->record($spec->table, $inserted, $updated, 0);
	}

	/** @param string[] $header */
	private function validateHeader(TableSpec $spec, string $path, array $header): void
	{
		$dbColumns = Schema::getColumnListing($spec->table);
		$unknown = array_diff($header, $dbColumns);
		if ($unknown !== []) {
			throw new RuntimeException(sprintf(
				'Unknown column(s) in %s header: %s. DB columns: %s',
				basename($path),
				implode(', ', $unknown),
				implode(', ', $dbColumns),
			));
		}
	}

	private function validateRow(TableSpec $spec, CsvRow $row): void
	{
		foreach ($spec->requiredCols as $col) {
			if ($row->get($col) === null || $row->get($col) === '') {
				throw new RuntimeException(sprintf(
					'Row %d of %s is missing required column "%s"',
					$row->lineNumber,
					$spec->csvFile,
					$col,
				));
			}
		}
	}

	/** @return array<string, mixed> */
	private function normalize(TableSpec $spec, CsvRow $row): array
	{
		$payload = $row->only($spec->columns);

		if (isset($payload['id'])) {
			$payload['id'] = (int) $payload['id'];
		}

		$payload = array_filter($payload, fn($v) => $v !== null);

		$now = now();
		$payload['created_at'] = $payload['created_at'] ?? $now;
		$payload['updated_at'] = $now;

		return $payload;
	}

	/** @return string[] */
	private function updatableColumns(TableSpec $spec): array
	{
		return array_values(array_diff($spec->columns, $spec->uniqueBy));
	}

	/**
	 * DESTRUCTIVE — DEV ONLY. See class docblock.
	 * @throws RuntimeException outside local/testing.
	 */
	private function truncate(TableSpec $spec): void
	{
		if (! app()->environment('local', 'testing')) {
			throw new RuntimeException(sprintf(
				'Refusing to TRUNCATE "%s": --truncate is only allowed in '
					. 'local or testing environments (current: %s).',
				$spec->table,
				app()->environment(),
			));
		}

		DB::statement(sprintf(
			'TRUNCATE TABLE "%s" RESTART IDENTITY CASCADE',
			$spec->table,
		));
	}
}
