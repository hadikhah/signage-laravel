<?php

namespace App\Services\Import;

use RuntimeException;

/**
 * Reads a CSV file into a header list + CsvRow objects.
 *
 * Format contract:
 *   - UTF-8, comma-delimited, first row is the header.
 *   - RFC 4180 quoting ("" for embedded quotes).
 *   - Empty cell → null.
 *   - Blank lines are skipped.
 *   - Row count mismatch → RuntimeException with line number.
 *
 * Uses escape: '' (RFC 4180). If a legacy export uses \" escaping
 * instead of "" doubling, preprocess the file first — this reader
 * will not parse non-RFC-4180 quoting.
 */
final class CsvReader
{
    /**
     * @return array{header: string[], rows: CsvRow[]}
     * @throws RuntimeException
     */
    public function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("CSV file not found: {$path}");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open CSV file: {$path}");
        }

        try {
            $header = $this->readHeader($handle, $path);
            $rows = [];
            $lineNo = 1;

            while (($fields = fgetcsv($handle, escape: '')) !== false) {
                $lineNo++;

                // Blank line = single null/empty field. Skip it.
                if (count($fields) === 1 && ($fields[0] === null || $fields[0] === '')) {
                    continue;
                }

                if (count($fields) !== count($header)) {
                    throw new RuntimeException(sprintf(
                        'Row %d of %s has %d fields, expected %d',
                        $lineNo,
                        basename($path),
                        count($fields),
                        count($header),
                    ));
                }

                $assoc = [];
                foreach ($header as $i => $col) {
                    $value = $fields[$i];
                    $assoc[$col] = ($value === '' || $value === null) ? null : $value;
                }

                $rows[] = new CsvRow($lineNo, $assoc);
            }

            return ['header' => $header, 'rows' => $rows];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     * @return string[]
     */
    private function readHeader($handle, string $path): array
    {
        $header = fgetcsv($handle, escape: '');

        if ($header === false || $header === [null]) {
            throw new RuntimeException("CSV file is empty: {$path}");
        }

        return array_map(
            fn($h) => trim((string) $h, " \t\n\r\0\xEF\xBB\xBF"),
            $header,
        );
    }
}
