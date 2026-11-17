<?php

use App\Models\ApplicationSetting;
use App\Models\City;
use App\Models\Content;
use App\Models\Department;
use App\Models\Monitor;
use App\Models\MonitorContent;
use App\Models\Office;
use App\Models\View;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Structural contract tests for the playback endpoint.
 *
 * Fixtures in tests/Fixtures/legacy/api/ record the shape we believe the
 * legacy client expects. See ADR-0008 — these are golden files, replaceable
 * when real legacy data arrives.
 *
 * Assertions:
 *   - same keys (recursive)
 *   - same casing (keys compared case-sensitively)
 *   - same types (int / string / bool / null / array)
 *   - same enum string values (ContentTypeText)
 *
 * Not asserted:
 *   - dynamic values (ContentId, dates, timestamps) — only types / formats
 */
beforeEach(function () {
    // Freeze time so MonitorDateTime is deterministic
    // Carbon::setTestNow(Carbon::parse('2025-11-07 12:00:00'));

    $city = City::factory()->create();
    $office = Office::create(['name' => 'HQ', 'city_id' => $city->id]);
    $dept = Department::create([
        'department_name' => 'IT',
        'office_id' => $office->id,
        'is_child' => false,
        'daily_message' => 'Welcome to IT',
    ]);

    ApplicationSetting::query()->delete();
    ApplicationSetting::create([
        'id' => 1,
        'bypass_all_content' => false,
        'active_base_content' => false,
        'last_update' => null,
    ]);

    $this->dept = $dept;
});

// afterEach(function () {
//     Carbon::setTestNow();
// });

// -----------------------------------------------------------------------------
// Structural comparison helper
// -----------------------------------------------------------------------------

/**
 * Recursively walk $expected; assert $actual has same keys, casing, types.
 * Skips value comparison except for enum-like string fields.
 *
 * @param  string  $path  Human-readable path for failure messages
 */
function assertStructureMatches(mixed $actual, mixed $expected, string $path = '$'): void
{
    if (is_array($expected)) {
        expect($actual)->toBeArray("$path: expected array, got ".gettype($actual));

        // Keys — order-independent, exact casing
        expect(array_keys($actual))->toEqualCanonicalizing(
            array_keys($expected),
            "$path: key mismatch",
        );

        foreach ($expected as $key => $sub) {
            if (! array_key_exists($key, $actual)) {
                expect(false)->toBeTrue("$path.$key: missing in actual");
            }
            assertStructureMatches($actual[$key], $sub, "$path.$key");
        }

        return;
    }

    if ($expected === null) {
        expect($actual)->toBeNull("$path: expected null");

        return;
    }

    // Type assertions — content-agnostic
    match (gettype($expected)) {
        'string' => expect($actual)->toBeString("$path: expected string"),
        'integer' => expect($actual)->toBeInt("$path: expected int"),
        'boolean' => expect($actual)->toBeBool("$path: expected bool"),
        'double' => expect($actual)->toBeFloat("$path: expected float"),
        default => null,
    };

    // Known enum fields: exact value check
    if (str_ends_with($path, '.ContentTypeText')) {
        expect($actual)->toBe($expected, "$path: enum mismatch");
    }
}

function fixtureJson(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/../../Fixtures/legacy/api/{$name}.json"), true);
}

// -----------------------------------------------------------------------------
// Branch: default
// -----------------------------------------------------------------------------

it('playback-default matches fixture structure', function () {
    $m = Monitor::create([
        'ip_address' => '10.0.0.1',
        'department_id' => $this->dept->id,
        'status' => 'online',
    ]);
    $c = Content::factory()->create([
        'client_file_name' => 'original.jpg',           // ← add to every Content factory call
        'content_name' => 'welcome.jpg',
        'content_type' => 1,
        'deleted' => false,
        'user_id' => null,           // ← add: fixture expects UserId=null
        'from_date' => null,           // ← add: no date window
        'to_date' => null,           // ← add
        'uploaded_at' => Carbon::parse('2026-01-01 00:00:00'),
        'order' => 1,
    ]);
    MonitorContent::create([
        'monitor_id' => $m->id,
        'content_id' => $c->id,
        'content_order' => 1,
        'display_time' => 10,
        'active' => true,
    ]);

    $response = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1')->json();

    assertStructureMatches($response, fixtureJson('playback-default'));
});

// -----------------------------------------------------------------------------
// Branch: share (bypass)
// -----------------------------------------------------------------------------

it('playback-share matches fixture structure', function () {
    ApplicationSetting::current()->update(['bypass_all_content' => true]);

    $m = Monitor::create([
        'ip_address' => '10.0.0.1',
        'department_id' => $this->dept->id,
        'status' => 'online',
    ]);
    $c = Content::factory()->create([
        'client_file_name' => null,           // ← add to every Content factory call
        'content_name' => 'shared.jpg',
        'content_type' => 1,
        'deleted' => false,
        'user_id' => null,           // ← add: fixture expects UserId=null
        'from_date' => null,           // ← add: no date window
        'to_date' => null,           // ← add
        'uploaded_at' => Carbon::parse('2026-01-02 00:00:00'),
    ]);

    DB::table('share_monitors')->insert([
        'monitor_id' => $m->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('share_contents')->insert([
        'content_id' => $c->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1')->json();

    assertStructureMatches($response, fixtureJson('playback-share'));
});

// -----------------------------------------------------------------------------
// Branch: view
// -----------------------------------------------------------------------------

it('playback-view matches fixture structure', function () {
    $m = Monitor::create([
        'ip_address' => '10.0.0.1',
        'department_id' => $this->dept->id,
        'status' => 'online',
    ]);
    $view = View::create(['name' => 'morning']);
    $c = Content::factory()->create([
        'client_file_name' => null,           // ← add to every Content factory call
        'content_name' => 'welcome.jpg',
        'content_type' => 1,
        'deleted' => false,
        'user_id' => null,           // ← add: fixture expects UserId=null
        'from_date' => null,           // ← add: no date window
        'to_date' => null,           // ← add
        'uploaded_at' => Carbon::parse('2026-01-01 00:00:00'),
    ]);

    DB::table('monitor_views')->insert([
        'monitor_id' => $m->id,
        'view_id' => $view->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('view_contents')->insert([
        'view_id' => $view->id,
        'content_id' => $c->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    MonitorContent::create([
        'monitor_id' => $m->id,
        'content_id' => $c->id,
        'content_order' => 1,
        'display_time' => 7,
        'active' => true,
    ]);

    $response = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1&view=morning')->json();

    assertStructureMatches($response, fixtureJson('playback-view'));
});

// -----------------------------------------------------------------------------
// Branch: base (append)
// -----------------------------------------------------------------------------

it('playback-base matches fixture structure', function () {
    ApplicationSetting::current()->update(['active_base_content' => true]);

    $m = Monitor::create([
        'ip_address' => '10.0.0.1',
        'department_id' => $this->dept->id,
        'status' => 'online',
    ]);
    $own = Content::factory()->create([
        'client_file_name' => null,           // ← add to every Content factory call
        'content_name' => 'own.jpg',
        'content_type' => 1,
        'deleted' => false,
        'user_id' => null,           // ← add: fixture expects UserId=null
        'from_date' => null,           // ← add: no date window
        'to_date' => null,           // ← add
        'uploaded_at' => Carbon::parse('2026-01-01 00:00:00'),
        'order' => 1,
    ]);
    $base = Content::factory()->create([
        'client_file_name' => null,           // ← add to every Content factory call
        'content_name' => 'base.jpg',
        'content_type' => 1,
        'deleted' => false,
        'user_id' => null,           // ← add: fixture expects UserId=null
        'from_date' => null,           // ← add: no date window
        'to_date' => null,           // ← add
        'uploaded_at' => Carbon::parse('2026-01-02 00:00:00'),
        'order' => 2,
    ]);
    MonitorContent::create([
        'monitor_id' => $m->id,
        'content_id' => $own->id,
        'content_order' => 1,
        'display_time' => 10,
        'active' => true,
    ]);
    DB::table('base_monitors')->insert([
        'monitor_id' => $m->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('base_contents')->insert([
        'content_id' => $base->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1')->json();

    assertStructureMatches($response, fixtureJson('playback-base'));
});

// -----------------------------------------------------------------------------
// Branch: empty (unknown monitor)
// -----------------------------------------------------------------------------

it('playback-empty matches fixture structure', function () {
    $response = $this->getJson('/api/v1/playback/monitor-contents?ip=10.99.99.99')->json();

    assertStructureMatches($response, fixtureJson('playback-empty'));
});

// -----------------------------------------------------------------------------
// Contract invariants — the fields that MUST NOT change
// -----------------------------------------------------------------------------

it('preserves legacy typo Contentpath (lowercase p)', function () {
    $m = Monitor::create([
        'ip_address' => '10.0.0.1',
        'department_id' => $this->dept->id,
    ]);
    $c = Content::factory()->create([
        'client_file_name' => null,           // ← add to every Content factory call
        'content_type' => 1,
        'content_name' => 'x.jpg',
    ]);
    MonitorContent::create([
        'monitor_id' => $m->id,
        'content_id' => $c->id,
        'content_order' => 1,
        'display_time' => 5,
        'active' => true,
    ]);

    $item = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1')
        ->json('Data.MonitorContents.0');

    expect($item)->toHaveKey('Contentpath');
    expect($item)->not->toHaveKey('ContentPath');
});

it('preserves legacy typo UploadeDateTime (extra e)', function () {
    $m = Monitor::create(['ip_address' => '10.0.0.1', 'department_id' => $this->dept->id]);
    $c = Content::factory()->create([
        'client_file_name' => null,           // ← add to every Content factory call
        'user_id' => null,           // ← add: fixture expects UserId=null
        'from_date' => null,           // ← add: no date window
        'to_date' => null,           // ← add
        'uploaded_at' => now(),
    ]);
    MonitorContent::create([
        'monitor_id' => $m->id,
        'content_id' => $c->id,
        'content_order' => 1,
        'display_time' => 5,
        'active' => true,
    ]);

    $item = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1')
        ->json('Data.MonitorContents.0');

    expect($item)->toHaveKey('UploadeDateTime');
    expect($item)->not->toHaveKey('UploadedDateTime');
});

it('preserves legacy typo ByPassAllContent (capital P)', function () {
    $r = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1')->json();

    expect($r['Data'])->toHaveKey('ByPassAllContent');
    expect($r['Data'])->not->toHaveKey('BypassAllContent');
});

it('does not return a Contents key (client adds it)', function () {
    $r = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1')->json();

    expect($r['Data'])->not->toHaveKey('Contents');
});

it('uses ResultCode 4000 for playback', function () {
    $r = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1')->json();

    expect($r['ResultCode'])->toBe(4000);
});

it('maps ContentTypeText correctly for every type', function () {
    $m = Monitor::create(['ip_address' => '10.0.0.1', 'department_id' => $this->dept->id]);

    $expected = [
        1 => 'عکس',
        2 => 'فیلم',
        3 => 'متن',
        4 => 'فلش',
        5 => 'آدرس',
    ];

    foreach ($expected as $type => $label) {
        Content::query()->delete();
        MonitorContent::query()->delete();

        $c = Content::factory()->create([
            'client_file_name' => null,           // ← add to every Content factory call
            'content_type' => $type,
        ]);
        MonitorContent::create([
            'monitor_id' => $m->id,
            'content_id' => $c->id,
            'content_order' => 1,
            'display_time' => 5,
            'active' => true,
        ]);

        $item = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1')
            ->json('Data.MonitorContents.0');

        expect($item['ContentTypeText'])->toBe($label, "type=$type");
    }
});
