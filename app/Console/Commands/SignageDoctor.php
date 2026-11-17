<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Models\Monitor;
use App\Models\View;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Health check for the signage database + media.
 *
 * Per TASK-01 Step 9:
 *   - media files exist for every content row;
 *   - every monitor has a department;
 *   - every view belongs to a monitor;
 *   - no orphan join rows.
 *
 * Exit codes:
 *   0 — all checks pass
 *   1 — one or more checks failed
 */
class SignageDoctor extends Command
{
    protected $signature = 'signage:doctor
        {--json : Output machine-readable JSON (for CI)}
        {--skip-media : Skip the (potentially slow) media filesystem check}';

    protected $description = 'Check signage database integrity and media file presence.';

    /** @var array<int, array{name: string, ok: bool, detail: string, issues?: array}> */
    private array $results = [];

    public function handle(): int
    {
        $this->checkMediaFiles();
        $this->checkMonitorDepartments();
        $this->checkViewMonitorLinkage();
        $this->checkOrphanJoinRows();

        $hasFailure = collect($this->results)->contains(fn ($r) => ! $r['ok']);

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => ! $hasFailure,
                'results' => $this->results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderHuman();
        }

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // 1. Media files
    // -------------------------------------------------------------------------

    private function checkMediaFiles(): void
    {
        if ($this->option('skip-media')) {
            $this->results[] = [
                'name' => 'media_files',
                'ok' => true,
                'detail' => 'skipped (--skip-media)',
            ];

            return;
        }

        // URL content (type 5) has no file; deleted rows are irrelevant.
        $contents = Content::query()
            ->where('deleted', false)
            ->where('content_type', '!=', 5)
            ->get(['id', 'content_name', 'content_type']);

        $disk = Storage::disk('media');
        $issues = [];

        foreach ($contents as $content) {
            $path = "files/{$content->id}/{$content->content_name}";
            if (! $disk->exists($path)) {
                $issues[] = [
                    'content_id' => $content->id,
                    'name' => $content->content_name,
                    'expected' => $disk->path($path),
                ];
            }
        }

        $this->results[] = [
            'name' => 'media_files',
            'ok' => $issues === [],
            'detail' => count($issues).'/'.$contents->count().' files missing',
            'issues' => $issues,
        ];
    }

    // -------------------------------------------------------------------------
    // 2. Monitor departments
    // -------------------------------------------------------------------------

    private function checkMonitorDepartments(): void
    {
        // FK prevents this in normal operation, but verify the assumption.
        $total = Monitor::count();
        $orphans = Monitor::whereNotIn(
            'department_id',
            DB::table('departments')->pluck('id'),
        )->get(['id', 'ip_address', 'department_id']);

        $this->results[] = [
            'name' => 'monitor_departments',
            'ok' => $orphans->isEmpty(),
            'detail' => $orphans->count()."/{$total} monitors have no department",
            'issues' => $orphans->map(fn ($m) => [
                'monitor_id' => $m->id,
                'ip_address' => $m->ip_address,
                'department_id' => $m->department_id,
            ])->all(),
        ];
    }

    // -------------------------------------------------------------------------
    // 3. View ↔ monitor linkage
    // -------------------------------------------------------------------------

    private function checkViewMonitorLinkage(): void
    {
        $total = View::count();
        $orphans = View::query()
            ->whereNotIn('id', DB::table('monitor_views')->pluck('view_id'))
            ->get(['id', 'name']);

        $this->results[] = [
            'name' => 'view_monitor_linkage',
            'ok' => $orphans->isEmpty(),
            'detail' => $orphans->count()."/{$total} views have no monitor",
            'issues' => $orphans->map(fn ($v) => [
                'view_id' => $v->id,
                'name' => $v->name,
            ])->all(),
        ];
    }

    // -------------------------------------------------------------------------
    // 4. Orphan join rows
    // -------------------------------------------------------------------------

    private function checkOrphanJoinRows(): void
    {
        $checks = [
            'monitor_contents' => $this->orphansFor('monitor_contents', [
                'monitor_id' => 'monitors',
                'content_id' => 'contents',
            ]),
            'view_contents' => $this->orphansFor('view_contents', [
                'view_id' => 'views',
                'content_id' => 'contents',
            ]),
            'monitor_views' => $this->orphansFor('monitor_views', [
                'monitor_id' => 'monitors',
                'view_id' => 'views',
            ]),
            'share_contents' => $this->orphansFor('share_contents', [
                'content_id' => 'contents',
            ]),
            'share_monitors' => $this->orphansFor('share_monitors', [
                'monitor_id' => 'monitors',
            ]),
            'base_contents' => $this->orphansFor('base_contents', [
                'content_id' => 'contents',
            ]),
            'base_monitors' => $this->orphansFor('base_monitors', [
                'monitor_id' => 'monitors',
            ]),
        ];

        $totalIssues = collect($checks)->sum(fn ($c) => count($c['issues']));

        $this->results[] = [
            'name' => 'orphan_join_rows',
            'ok' => $totalIssues === 0,
            'detail' => "{$totalIssues} orphan rows across ".count($checks).' tables',
            'issues' => collect($checks)
                ->filter(fn ($c) => $c['issues'] !== [])
                ->all(),
        ];
    }

    /**
     * For a join table, find rows whose FK columns point at missing parents.
     *
     * @param  array<string, string>  $fkToTable  column => parent table
     * @return array{issues: array<int, array>}
     */
    private function orphansFor(string $joinTable, array $fkToTable): array
    {
        $query = DB::table($joinTable);
        $hasOrphans = false;

        foreach ($fkToTable as $column => $parentTable) {
            $query->orWhereNotIn(
                "{$joinTable}.{$column}",
                DB::table($parentTable)->select('id'),
            );
            $hasOrphans = true;
        }

        if (! $hasOrphans) {
            return ['issues' => []];
        }

        $rows = $query->get()->toArray();

        return [
            'issues' => array_map(fn ($r) => (array) $r, $rows),
        ];
    }

    // -------------------------------------------------------------------------
    // Human output
    // -------------------------------------------------------------------------

    private function renderHuman(): void
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Signage doctor</>');
        $this->line('  '.str_repeat('=', 60));
        $this->newLine();

        foreach ($this->results as $r) {
            $icon = $r['ok'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("  {$icon} {$r['name']}  <fg=gray>{$r['detail']}</>");

            if (! $r['ok'] && ! empty($r['issues'])) {
                foreach (array_slice($r['issues'], 0, 10) as $issue) {
                    $this->line('      '.json_encode($issue, JSON_UNESCAPED_UNICODE));
                }
                if (count($r['issues']) > 10) {
                    $this->line('      ... and '.(count($r['issues']) - 10).' more');
                }
            }
        }

        $this->newLine();
        $failures = collect($this->results)->reject(fn ($r) => $r['ok'])->count();
        if ($failures === 0) {
            $this->line('  <fg=green;options=bold>All checks passed.</>');
        } else {
            $this->line("  <fg=red;options=bold>{$failures} check(s) failed.</>");
        }
        $this->newLine();
    }
}
