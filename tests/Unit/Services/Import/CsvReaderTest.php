<?php

use App\Services\Import\CsvReader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;


/**
 * Write rows to a temp CSV file and return its absolute path.
 *
 * @param  array<int, array<string, mixed>>  $rows
 * @param  string[]|null                     $header   Override header (default: keys of first row)
 */
function writeCsv(array $rows, ?array $header = null, ?string $dir = null): string
{
    $dir ??= sys_get_temp_dir();
    $path = $dir . '/csv-' . Str::random(12) . '.csv';

    $header ??= array_keys($rows[0] ?? []);
    File::ensureDirectoryExists(dirname($path));

    $handle = fopen($path, 'wb');
    fputcsv($handle, $header, escape: '\\');

    foreach ($rows as $row) {
        fputcsv(
            $handle,
            array_map(fn($col) => $row[$col] ?? null, $header),
            escape: '\\',
        );
    }
    fclose($handle);

    return $path;
}

/**
 * Build a <table>.csv file inside $dir from a list of attribute arrays.
 *
 * @param  array<int, array<string, mixed>>  $rows
 * @param  string[]|null                     $header
 */
function writeTableCsv(string $dir, string $table, array $rows, ?array $header = null): string
{
    $path = "{$dir}/{$table}.csv";
    $header ??= array_keys($rows[0] ?? []);
    File::ensureDirectoryExists($dir);

    $handle = fopen($path, 'wb');
    fputcsv($handle, $header, escape: '\\');
    foreach ($rows as $row) {
        fputcsv(
            $handle,
            array_map(fn($col) => $row[$col] ?? null, $header),
            escape: '\\',
        );
    }
    fclose($handle);

    return $path;
}

/**
 * Convenience: assert a row exists in a table by id.
 */
function assertRowInTable(string $table, int $id, array $expected = []): void
{
    $row = \Illuminate\Support\Facades\DB::table($table)->where('id', $id)->first();
    expect($row)->not->toBeNull("Row {$id} not found in {$table}");

    foreach ($expected as $col => $value) {
        expect($row->{$col})->toBe(
            $value,
            "Column {$col} of {$table}#{$id} expected [{$value}], got [{$row->{$col}}]",
        );
    }
}

it('reads a valid csv into header and rows', function () {
    $path = writeCsv([
        ['id' => '1', 'name' => 'Tehran'],
        ['id' => '2', 'name' => 'Isfahan'],
    ]);

    $result = (new CsvReader())->read($path);

    expect($result['header'])->toBe(['id', 'name']);
    expect($result['rows'])->toHaveCount(2);
    expect($result['rows'][0]->values)->toBe(['id' => '1', 'name' => 'Tehran']);
    expect($result['rows'][0]->lineNumber)->toBe(2);
    expect($result['rows'][1]->lineNumber)->toBe(3);
});

it('treats empty cells as null', function () {
    $path = writeCsv([
        ['id' => '1', 'name' => 'A', 'note' => ''],
        ['id' => '2', 'name' => '',  'note' => 'x'],
    ]);

    $rows = (new CsvReader())->read($path)['rows'];

    expect($rows[0]->get('note'))->toBeNull();
    expect($rows[1]->get('name'))->toBeNull();
});

it('skips blank lines', function () {
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, "id,name\n1,A\n\n2,B\n");

    $rows = (new CsvReader())->read($path)['rows'];

    expect($rows)->toHaveCount(2);
});

it('fails with a clear row number on a malformed row', function () {
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, "id,name\n1,A\n2\n3,C\n");

    expect(fn() => (new CsvReader())->read($path))
        ->toThrow(RuntimeException::class, 'Row 3 of');
});

it('fails when the file is missing', function () {
    expect(fn() => (new CsvReader())->read('/does/not/exist.csv'))
        ->toThrow(RuntimeException::class, 'CSV file not found');
});

it('fails on an empty file', function () {
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, '');

    expect(fn() => (new CsvReader())->read($path))
        ->toThrow(RuntimeException::class, 'empty');
});

it('trims a UTF-8 BOM from the header', function () {
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, "\xEF\xBB\xBFid,name\n1,A\n");

    $header = (new CsvReader())->read($path)['header'];

    expect($header)->toBe(['id', 'name']);
});
