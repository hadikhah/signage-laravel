<?php

use App\Models\City;
use App\Models\Content;
use App\Models\Department;
use App\Models\Monitor;
use App\Models\MonitorContent;
use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->exportDir = sys_get_temp_dir() . '/signage-export-' . uniqid();
    File::ensureDirectoryExists($this->exportDir);
});

afterEach(function () {
    File::deleteDirectory($this->exportDir);
});


// =============================================================================
// Helper — writeTableCsv (local fallback if not defined in tests/Pest.php)
// =============================================================================

if (! function_exists('writeTableCsv')) {
    function writeTableCsv(string $dir, string $table, array $rows, ?array $header = null): string
    {
        File::ensureDirectoryExists($dir);
        $path = "{$dir}/{$table}.csv";
        $header ??= array_keys($rows[0] ?? []);

        $handle = fopen($path, 'wb');
        fputcsv($handle, $header, escape: '');
        foreach ($rows as $row) {
            fputcsv(
                $handle,
                array_map(fn($col) => $row[$col] ?? null, $header),
                escape: '',
            );
        }
        fclose($handle);

        return $path;
    }
}



// =============================================================================
// Fixture builder — one CSV per table, parents before children
// =============================================================================

if (! function_exists('seedExportDir')) {
    /**
     * @return array<string, int>  table → row count written to CSV
     */
    function seedExportDir(string $dir, int $perTable = 3): array
    {
        // --- cities ---------------------------------------------------------
        $cities = [];
        for ($i = 1; $i <= $perTable; $i++) {
            $cities[] = ['id' => $i, 'name' => "City {$i}"];
        }
        writeTableCsv($dir, 'cities', $cities);

        // --- application_settings (singleton) ------------------------------
        $settings = [[
            'id' => 1,
            'bypass_all_content' => 0,
            'active_base_content' => 0,
            'last_update' => null,
        ]];
        writeTableCsv($dir, 'application_settings', $settings);

        // --- offices (2 per city) ------------------------------------------
        $offices = [];
        $officeId = 1;
        foreach ($cities as $city) {
            for ($j = 0; $j < 2; $j++) {
                $offices[] = [
                    'id' => $officeId,
                    'name' => "Office {$officeId}",
                    'city_id' => $city['id'],
                ];
                $officeId++;
            }
        }
        writeTableCsv($dir, 'offices', $offices);

        // --- departments (1 parent + 1 child per office) -------------------
        $departments = [];
        $deptId = 1;
        foreach ($offices as $office) {
            $parentId = $deptId;
            $departments[] = [
                'id' => $deptId++,
                'department_name' => "Parent Dept of Office {$office['id']}",
                'daily_message' => null,
                'is_child' => 0,
                'lock' => null,
                'parent_id' => null,
                'office_id' => $office['id'],
            ];
            $departments[] = [
                'id' => $deptId++,
                'department_name' => "Child Dept of Office {$office['id']}",
                'daily_message' => null,
                'is_child' => 1,
                'lock' => null,
                'parent_id' => $parentId,
                'office_id' => $office['id'],
            ];
        }
        writeTableCsv($dir, 'departments', $departments);

        // --- monitors (1 per department) -----------------------------------
        $monitors = [];
        $monitorId = 1;
        foreach ($departments as $dept) {
            $monitors[] = [
                'id' => $monitorId,
                'ip_address' => "10.0.0.{$monitorId}",
                'host_name' => "host-{$monitorId}",
                'department_id' => $dept['id'],
                'mac_address' => sprintf('AA:BB:CC:00:00:%02X', $monitorId),
                'floor' => (string) (($monitorId % 3) + 1),
                'wing' => ['north', 'south', 'east', 'west'][$monitorId % 4],
                'status' => 'online',
                'lock' => null,
                'description' => null,
                'platform' => null,
                'owner' => null,
            ];
            $monitorId++;
        }
        writeTableCsv($dir, 'monitors', $monitors);

        // --- content_url_user_passwords ------------------------------------
        $credentials = [[
            'id' => 1,
            'url' => 'https://example.com',
            'user_name' => 'demo',
            'password' => 'demo123',
        ]];
        writeTableCsv($dir, 'content_url_user_passwords', $credentials);

        // --- contents (2 file + 1 URL) -------------------------------------
        $contents = [
            [
                'id' => 1,
                'content_name' => 'welcome.jpg',
                'client_file_name' => null,
                'user_id' => null,
                'content_type' => 1,
                'is_auto_login' => 0,
                'deleted' => 0,
                'order' => 1,
                'uploaded_at' => '2026-01-01 00:00:00',
                'from_date' => null,
                'to_date' => null,
                'content_url_user_password_id' => null,
            ],
            [
                'id' => 2,
                'content_name' => 'intro.mp4',
                'client_file_name' => null,
                'user_id' => null,
                'content_type' => 2,
                'is_auto_login' => 0,
                'deleted' => 0,
                'order' => 2,
                'uploaded_at' => '2026-01-02 00:00:00',
                'from_date' => null,
                'to_date' => null,
                'content_url_user_password_id' => null,
            ],
            [
                'id' => 3,
                'content_name' => 'Example Website',
                'client_file_name' => 'example-url',
                'user_id' => null,
                'content_type' => 5,
                'is_auto_login' => 1,
                'deleted' => 0,
                'order' => 3,
                'uploaded_at' => '2026-01-03 00:00:00',
                'from_date' => null,
                'to_date' => null,
                'content_url_user_password_id' => 1,
            ],
        ];
        writeTableCsv($dir, 'contents', $contents);

        // --- monitor_contents (2 per monitor: contents 1, 2) ---------------
        $monitorContents = [];
        $mcId = 1;
        foreach ($monitors as $monitor) {
            $order = 1;
            foreach ([1, 2] as $contentId) {
                $monitorContents[] = [
                    'id' => $mcId++,
                    'monitor_id' => $monitor['id'],
                    'content_id' => $contentId,
                    'content_order' => $order++,
                    'display_time' => 10,
                    'active' => 1,
                ];
            }
        }
        writeTableCsv($dir, 'monitor_contents', $monitorContents);

        // --- views ---------------------------------------------------------
        $views = [
            ['id' => 1, 'name' => 'morning'],
            ['id' => 2, 'name' => 'evening'],
        ];
        writeTableCsv($dir, 'views', $views);

        // --- view_contents -------------------------------------------------
        $viewContents = [
            ['id' => 1, 'view_id' => 1, 'content_id' => 1],
            ['id' => 2, 'view_id' => 1, 'content_id' => 2],
            ['id' => 3, 'view_id' => 2, 'content_id' => 2],
            ['id' => 4, 'view_id' => 2, 'content_id' => 3],
        ];
        writeTableCsv($dir, 'view_contents', $viewContents);

        // --- monitor_views -------------------------------------------------
        $monitorViews = [
            ['id' => 1, 'monitor_id' => 1, 'view_id' => 1],
            ['id' => 2, 'monitor_id' => 2, 'view_id' => 2],
        ];
        writeTableCsv($dir, 'monitor_views', $monitorViews);

        // --- share ---------------------------------------------------------
        $shareContents = [['id' => 1, 'content_id' => 2]];
        $shareMonitors = [['id' => 1, 'monitor_id' => 1]];
        writeTableCsv($dir, 'share_contents', $shareContents);
        writeTableCsv($dir, 'share_monitors', $shareMonitors);

        // --- base ----------------------------------------------------------
        $baseContents = [['id' => 1, 'content_id' => 3]];
        $baseMonitors = [['id' => 1, 'monitor_id' => 1]];
        writeTableCsv($dir, 'base_contents', $baseContents);
        writeTableCsv($dir, 'base_monitors', $baseMonitors);

        return [
            'cities' => count($cities),
            'application_settings' => count($settings),
            'offices' => count($offices),
            'departments' => count($departments),
            'monitors' => count($monitors),
            'content_url_user_passwords' => count($credentials),
            'contents' => count($contents),
            'monitor_contents' => count($monitorContents),
            'views' => count($views),
            'view_contents' => count($viewContents),
            'monitor_views' => count($monitorViews),
            'share_contents' => count($shareContents),
            'share_monitors' => count($shareMonitors),
            'base_contents' => count($baseContents),
            'base_monitors' => count($baseMonitors),
        ];
    }
}

// =============================================================================
// Happy path
// =============================================================================

it('imports every currently-configured table', function () {
    $expected = seedExportDir($this->exportDir, perTable: 2);

    $this->artisan('signage:import-legacy', ['--path' => $this->exportDir])
        ->assertExitCode(0);

    foreach ($expected as $table => $count) {
        expect(DB::table($table)->count())
            ->toBe($count, "Table {$table} should have {$count} rows");
    }
});

it('preserves ids from the CSV', function () {
    writeTableCsv($this->exportDir, 'cities', [
        ['id' => 1001, 'name' => 'A'],
        ['id' => 2002, 'name' => 'B'],
        ['id' => 3003, 'name' => 'C'],
    ]);
    writeTableCsv($this->exportDir, 'application_settings', [[
        'id' => 1,
        'bypass_all_content' => 0,
        'active_base_content' => 0,
        'last_update' => null,
    ]]);

    $this->artisan('signage:import-legacy', [
        '--path' => $this->exportDir,
        '--only' => 'cities,application_settings',
    ])->assertExitCode(0);

    expect(City::pluck('id')->sort()->values()->all())->toBe([1001, 2002, 3003]);
    expect(City::where('id', 2002)->value('name'))->toBe('B');
});

// =============================================================================
// Idempotency
// =============================================================================

it('is idempotent — running twice does not duplicate rows', function () {
    $expected = seedExportDir($this->exportDir, perTable: 2);

    $this->artisan('signage:import-legacy', ['--path' => $this->exportDir])
        ->assertExitCode(0);

    $afterFirst = collect($expected)
        ->map(fn($_, $t) => DB::table($t)->count())
        ->all();

    $this->artisan('signage:import-legacy', ['--path' => $this->exportDir])
        ->assertExitCode(0);

    $afterSecond = collect($expected)
        ->map(fn($_, $t) => DB::table($t)->count())
        ->all();

    expect($afterSecond)->toBe($afterFirst);
});

it('updates changed values on second run without adding rows', function () {
    seedExportDir($this->exportDir, perTable: 2);

    $this->artisan('signage:import-legacy', ['--path' => $this->exportDir])
        ->assertExitCode(0);

    $originalCount = City::count();

    writeTableCsv($this->exportDir, 'cities', [
        ['id' => 1, 'name' => 'Renamed City'],
    ]);

    $this->artisan('signage:import-legacy', [
        '--path' => $this->exportDir,
        '--only' => 'cities',
    ])->assertExitCode(0);

    expect(City::count())->toBe($originalCount);
    expect(City::find(1)->name)->toBe('Renamed City');
});

// =============================================================================
// REPLACE semantics — nulls overwrite existing values
// =============================================================================

it('overwrites existing values with null when CSV cell is empty (REPLACE semantics)', function () {
    seedExportDir($this->exportDir, perTable: 1);

    $this->artisan('signage:import-legacy', ['--path' => $this->exportDir])
        ->assertExitCode(0);

    $dept = Department::first();
    $dept->update([
        'daily_message' => 'temp message',
        'lock'          => true,          // ← boolean, not string
    ]);

    expect($dept->fresh()->daily_message)->toBe('temp message');
    expect($dept->fresh()->lock)->toBeTrue();

    $departments = Department::all()->map(fn ($d) => [
        'id'              => $d->id,
        'department_name' => $d->department_name,
        'daily_message'   => '',          // empty → null
        'is_child'        => $d->is_child ? 1 : 0,
        'lock'            => '',          // empty → null (only works if nullable)
        'parent_id'       => $d->parent_id,
        'office_id'       => $d->office_id,
    ])->all();

    writeTableCsv($this->exportDir, 'departments', $departments);

    $status = $this->artisan('signage:import-legacy', [
        '--path' => $this->exportDir,
        '--only' => 'departments',
    ])->run();

    if ($status !== 0) {
        dump('Import failed — check the artisan output above');
    }
    expect($status)->toBe(0);

    // daily_message is text → null-overwrite proven here
    expect($dept->fresh()->daily_message)->toBeNull();

    // lock: only assert if nullable; if NOT NULL, the reimport would have failed
    // (which is why we test $status above first)
    expect($dept->fresh()->lock)->toBeNull();
});

it('overwrites existing values when CSV provides a new value', function () {
    writeTableCsv($this->exportDir, 'cities', [['id' => 1, 'name' => 'Old Name']]);
    writeTableCsv($this->exportDir, 'application_settings', [[
        'id' => 1,
        'bypass_all_content' => 0,
        'active_base_content' => 0,
        'last_update' => null,
    ]]);

    $this->artisan('signage:import-legacy', [
        '--path' => $this->exportDir,
        '--only' => 'cities,application_settings',
    ])->assertExitCode(0);

    expect(City::find(1)->name)->toBe('Old Name');

    writeTableCsv($this->exportDir, 'cities', [['id' => 1, 'name' => 'New Name']]);

    $this->artisan('signage:import-legacy', [
        '--path' => $this->exportDir,
        '--only' => 'cities',
    ])->assertExitCode(0);

    expect(City::find(1)->name)->toBe('New Name');
});

// =============================================================================
// --dry-run
// =============================================================================

it('--dry-run touches nothing', function () {
    seedExportDir($this->exportDir, perTable: 2);

    $this->artisan('signage:import-legacy', [
        '--path' => $this->exportDir,
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect(City::count())->toBe(0);
    expect(Office::count())->toBe(0);
    expect(Department::count())->toBe(0);
    expect(Monitor::count())->toBe(0);
    expect(Content::count())->toBe(0);
    expect(MonitorContent::count())->toBe(0);
});

// =============================================================================
// --only
// =============================================================================

it('--only restricts to the requested tables', function () {
    seedExportDir($this->exportDir, perTable: 2);

    $this->artisan('signage:import-legacy', [
        '--path' => $this->exportDir,
        '--only' => 'cities',
    ])->assertExitCode(0);

    expect(City::count())->toBeGreaterThan(0);
    expect(Office::count())->toBe(0);
    expect(Department::count())->toBe(0);
    expect(Monitor::count())->toBe(0);
});

it('--only rejects unknown table names', function () {
    seedExportDir($this->exportDir, perTable: 1);

    $this->artisan('signage:import-legacy', [
        '--path' => $this->exportDir,
        '--only' => 'does_not_exist',
    ])->assertExitCode(1);
});

// =============================================================================
// Validation
// =============================================================================

it('rejects unknown columns in the CSV header', function () {
    writeTableCsv($this->exportDir, 'cities', [
        ['id' => 1, 'name' => 'X', 'nonexistent_col' => 'oops'],
    ]);

    $this->artisan('signage:import-legacy', [
        '--path' => $this->exportDir,
        '--only' => 'cities',
    ])->assertExitCode(1);

    expect(City::count())->toBe(0);
});

it('fails when a required column is missing in a row', function () {
    writeTableCsv($this->exportDir, 'cities', [
        ['id' => 1],   // missing "name"
    ], header: ['id', 'name']);

    $this->artisan('signage:import-legacy', [
        '--path' => $this->exportDir,
        '--only' => 'cities',
    ])->assertExitCode(1);
});

// =============================================================================
// --truncate
// =============================================================================

it('rejects --truncate outside local/testing', function () {
    $this->app['env'] = 'production';

    seedExportDir($this->exportDir, perTable: 1);

    $this->artisan('signage:import-legacy', [
        '--path' => $this->exportDir,
        '--truncate' => true,
    ])->assertExitCode(1);

    expect(City::count())->toBe(0);
});

it('--truncate wipes and re-imports in local (non-interactive)', function () {
    // Pre-existing data that must disappear
    City::factory()->create(['name' => 'Stale Row']);

    seedExportDir($this->exportDir, perTable: 2);

    $this->artisan('signage:import-legacy', [
        '--path' => $this->exportDir,
        '--truncate' => true,
        '--no-interaction' => true,   // ← add

    ])->assertExitCode(0);

    // Stale row gone, fresh rows imported
    expect(City::where('name', 'Stale Row')->exists())->toBeFalse();
    expect(City::count())->toBe(2);
});

// =============================================================================
// Sequence reset
// =============================================================================

it('resets postgres id sequences after import', function () {
    writeTableCsv($this->exportDir, 'cities', [
        ['id' => 500, 'name' => 'A'],
        ['id' => 501, 'name' => 'B'],
    ]);
    writeTableCsv($this->exportDir, 'application_settings', [[
        'id' => 1,
        'bypass_all_content' => 0,
        'active_base_content' => 0,
        'last_update' => null,
    ]]);

    $this->artisan('signage:import-legacy', [
        '--path' => $this->exportDir,
        '--only' => 'cities,application_settings',
    ])->assertExitCode(0);

    // After sequence reset, next auto-increment should be 502 (not 1, not 500)
    $newCity = City::create(['name' => 'Auto-increment city']);
    expect($newCity->id)->toBe(502);
});
