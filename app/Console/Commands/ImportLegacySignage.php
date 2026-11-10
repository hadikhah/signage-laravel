<?php

namespace App\Console\Commands;

use App\Services\Import\ImportContext;
use App\Services\Import\TableImporter;
use App\Services\Import\TableSpecs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportLegacySignage extends Command
{
    protected $signature = 'signage:import-legacy
        {--path= : Directory containing <table>.csv files (default: storage/app/legacy/export)}
        {--dry-run : Parse and report counts, touch nothing}
        {--truncate : TRUNCATE target tables before import (DEV ONLY, DESTRUCTIVE)}
        {--only= : Comma-separated subset of tables to import}';

    protected $description = 'Import legacy digital-signage CSV exports. WARNING: --truncate is destructive and DEV-ONLY.';

    public function handle(TableImporter $importer): int
    {
        // Gate 1: --truncate must not run outside local/testing.
        if ($this->option('truncate') && ! app()->environment('local', 'testing')) {
            $this->error('--truncate is only allowed in local/testing environments.');
            $this->line('Current environment: ' . app()->environment());
            return self::FAILURE;
        }

        // Gate 2: interactive confirmation (skipped when --no-interaction or non-TTY).
        if (
            $this->option('truncate')
            && $this->input->isInteractive()
            && ! $this->confirm(
                'WARNING: --truncate will DROP ALL ROWS from the target tables '
                    . 'and CASCADE to related tables. This is destructive and DEV ONLY. '
                    . 'Continue?',
                default: false,
            )
        ) {
            $this->warn('Import aborted.');
            return self::FAILURE;
        }

        $path = $this->resolvePath();
        if ($path === null) {
            return self::FAILURE;
        }

        $specs = $this->resolveSpecs();
        if ($specs === []) {
            return self::FAILURE;
        }

        $ctx = new ImportContext(
            dryRun: (bool) $this->option('dry-run'),
            truncate: (bool) $this->option('truncate'),
        );

        $this->info("Path:   {$path}");
        $this->info('Mode:   ' . ($ctx->dryRun ? 'DRY-RUN' : 'write'));
        $this->info('Tables: ' . implode(', ', array_map(fn($s) => $s->table, $specs)));
        $this->newLine();

        foreach ($specs as $spec) {
            try {
                $importer->import($spec, $path, $ctx);
                $c = $ctx->perTable[$spec->table];
                $this->line(sprintf(
                    '  %-22s +%d  ~%d  =%d',
                    $spec->table,
                    $c['inserted'],
                    $c['updated'],
                    $c['skipped'],
                ));
            } catch (RuntimeException $e) {
                $this->error("  {$spec->table}: {$e->getMessage()}");
                return self::FAILURE;
            }
        }

        if (! $ctx->dryRun) {
            $this->resetPostgresSequences(array_map(fn($s) => $s->table, $specs));
        }

        $this->newLine();
        $this->summarize($ctx);

        return $ctx->hasErrors() ? self::FAILURE : self::SUCCESS;
    }

    private function resolvePath(): ?string
    {
        $path = $this->option('path') ?? storage_path('app/legacy/export');

        if (! is_dir($path)) {
            $this->error("Import directory not found: {$path}");
            return null;
        }

        return rtrim($path, '/');
    }

    /** @return \App\Services\Import\TableSpec[] */
    private function resolveSpecs(): array
    {
        $all  = TableSpecs::all();
        $only = $this->option('only');

        if ($only === null || $only === '') {
            return $all;
        }

        $requested = array_map('trim', explode(',', $only));
        $known     = array_map(fn($s) => $s->table, $all);
        $unknown   = array_diff($requested, $known);

        if ($unknown !== []) {
            $this->error('Unknown table(s): ' . implode(', ', $unknown));
            $this->line('Known: ' . implode(', ', $known));
            return [];
        }

        return array_values(array_filter($all, fn($s) => in_array($s->table, $requested, true)));
    }

    /** @param string[] $tables */
    private function resetPostgresSequences(array $tables): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tables as $table) {
            DB::statement(sprintf(
                "SELECT setval(pg_get_serial_sequence('%s', 'id'), "
                    . "COALESCE((SELECT MAX(id) FROM \"%s\"), 0) + 1, false)",
                $table,
                $table,
            ));
        }

        $this->line('Sequences reset: ' . implode(', ', $tables));
    }

    private function summarize(ImportContext $ctx): void
    {
        $this->line(sprintf(
            'Totals: inserted=%d  updated=%d  skipped=%d',
            $ctx->inserted,
            $ctx->updated,
            $ctx->skipped,
        ));

        if ($ctx->orphans->isNotEmpty()) {
            $this->warn("Orphans: {$ctx->orphans->count()}");
            foreach ($ctx->orphans->take(10) as $o) {
                $this->line("  {$o['table']}:{$o['rowNumber']} {$o['message']}");
            }
        }

        if ($ctx->hasErrors()) {
            $this->error("Errors: {$ctx->errors->count()}");
        }
    }
}
