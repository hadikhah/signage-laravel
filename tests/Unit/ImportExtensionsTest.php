<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ImportExtensionsTest extends TestCase
{
    public function test_csv_and_spreadsheet_extensions_are_available(): void
    {
        foreach ([
            'ctype', 'dom', 'fileinfo', 'filter', 'gd', 'iconv', 'libxml',
            'mbstring', 'SimpleXML', 'xml', 'xmlreader', 'xmlwriter', 'zip', 'zlib',
        ] as $extension) {
            $this->assertTrue(extension_loaded($extension), "Missing PHP extension: {$extension}");
        }

        $this->assertTrue(function_exists('fgetcsv'));
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "id,name\n1,Signage\n");
        rewind($stream);

        $this->assertSame(['id', 'name'], fgetcsv($stream, escape: ''));
        $this->assertSame(['1', 'Signage'], fgetcsv($stream, escape: ''));

        fclose($stream);
    }
}
