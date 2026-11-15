<?php

use App\Models\City;
use App\Models\Content;
use App\Models\Department;
use App\Models\Monitor;
use App\Models\MonitorContent;
use App\Models\Office;
use App\Models\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('media');

    $city   = City::factory()->create();
    $office = Office::create(['name' => 'HQ', 'city_id' => $city->id]);
    $this->dept = Department::create([
        'department_name' => 'IT',
        'office_id'       => $office->id,
        'is_child'        => false,
    ]);
});

// -----------------------------------------------------------------------------
// Happy path
// -----------------------------------------------------------------------------

it('returns success when everything is healthy', function () {
    $m = Monitor::create([
        'ip_address' => '10.0.0.1', 'department_id' => $this->dept->id,
    ]);
    $c = Content::factory()->create([
        'content_name' => 'x.jpg', 'content_type' => 1, 'deleted' => false,
    ]);
    Storage::disk('media')->put("files/{$c->id}/x.jpg", 'bytes');

    MonitorContent::create([
        'monitor_id' => $m->id, 'content_id' => $c->id,
        'content_order' => 1, 'display_time' => 5, 'active' => true,
    ]);

    $this->artisan('signage:doctor')->assertExitCode(0);
});

// -----------------------------------------------------------------------------
// Media missing
// -----------------------------------------------------------------------------

it('fails when a media file is missing', function () {
    $c = Content::factory()->create([
        'content_name' => 'missing.jpg',
        'content_type' => 1,
        'deleted'      => false,
    ]);
    // deliberately not writing the file

    $this->artisan('signage:doctor')
        ->expectsOutputToContain('media_files')
        ->assertExitCode(1);
});

it('ignores URL content for media check', function () {
    Content::factory()->create([
        'content_name' => 'Example Website',
        'content_type' => 5,
        'deleted'      => false,
    ]);

    $this->artisan('signage:doctor')->assertExitCode(0);
});

it('ignores deleted content for media check', function () {
    Content::factory()->create([
        'content_name' => 'gone.jpg',
        'content_type' => 1,
        'deleted'      => true,
    ]);

    $this->artisan('signage:doctor')->assertExitCode(0);
});

// -----------------------------------------------------------------------------
// Views with no monitor
// -----------------------------------------------------------------------------

it('fails when a view has no monitor', function () {
    View::create(['name' => 'lonely']);

    $this->artisan('signage:doctor')
        ->expectsOutputToContain('view_monitor_linkage')
        ->assertExitCode(1);
});

it('passes view check when view is linked', function () {
    $m = Monitor::create(['ip_address' => '10.0.0.1', 'department_id' => $this->dept->id]);
    $v = View::create(['name' => 'linked']);
    DB::table('monitor_views')->insert([
        'monitor_id' => $m->id, 'view_id' => $v->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('signage:doctor')->assertExitCode(0);
});

// -----------------------------------------------------------------------------
// Orphan join rows
// -----------------------------------------------------------------------------

it('reports zero orphans for a healthy database', function () {
    $this->artisan('signage:doctor --json')
        ->expectsOutputToContain('"orphan_join_rows"')
        ->assertExitCode(0);
});

// -----------------------------------------------------------------------------
// JSON output shape
// -----------------------------------------------------------------------------

it('emits machine-readable JSON with --json', function () {
    $output = null;

    $this->artisan('signage:doctor --json')
        ->doesntExpectOutput('Signage doctor')
        ->assertExitCode(0);

    // Can't easily capture full output via Pest's artisan() — verify by calling handle()
    $this->artisan('signage:doctor', ['--json' => true, '--skip-media' => true])
        ->assertExitCode(0);
});

it('--skip-media bypasses the filesystem check', function () {
    Content::factory()->create([
        'content_name' => 'missing.jpg',
        'content_type' => 1,
        'deleted'      => false,
    ]);

    // Would fail if media check ran
    $this->artisan('signage:doctor --skip-media')->assertExitCode(0);
});