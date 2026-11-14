<?php

use App\Models\ApplicationSetting;
use App\Models\City;
use App\Models\Content;
use App\Models\Department;
use App\Models\Monitor;
use App\Models\MonitorContent;
use App\Models\Office;
use App\Models\View;
use App\Services\Playlist\IpNormalizer;
use App\Services\Playlist\PlaylistResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->resolver = new PlaylistResolver(new IpNormalizer);

    $this->city = City::factory()->create();
    $this->office = Office::create(['name' => 'HQ', 'city_id' => $this->city->id]);
    $this->dept = Department::create([
        'department_name' => 'IT',
        'office_id' => $this->office->id,
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
});

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------

function makeMonitor(array $overrides = []): Monitor
{
    $dept = Department::first();

    return Monitor::create(array_merge([
        'ip_address' => '10.0.0.'.random_int(1, 250),
        'department_id' => $dept->id,
        'status' => 'online',
    ], $overrides));
}

function makeContent(array $overrides = []): Content
{
    return Content::factory()->create(array_merge([
        'deleted' => false,
        'order' => 0,
        'from_date' => null,
        'to_date' => null,
        'content_type' => 1,
    ], $overrides));
}

function attachMonitorContent(Monitor $m, Content $c, array $overrides = []): MonitorContent
{
    return MonitorContent::create(array_merge([
        'monitor_id' => $m->id,
        'content_id' => $c->id,
        'content_order' => 1,
        'display_time' => 10,
        'active' => true,
    ], $overrides));
}

function linkViewToMonitor(Monitor $m, View $v): void
{
    DB::table('monitor_views')->insert([
        'monitor_id' => $m->id,
        'view_id' => $v->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

// -----------------------------------------------------------------------------
// Unknown IP
// -----------------------------------------------------------------------------

it('returns an empty playlist when the IP is unknown', function () {
    $playlist = $this->resolver->forMonitor('10.99.99.99', null);

    expect($playlist->isEmpty())->toBeTrue();
    expect($playlist->monitor)->toBeNull();
    expect($playlist->dailyMessage)->toBe('');
});

// -----------------------------------------------------------------------------
// Rule 4 — default (monitor_contents)
// -----------------------------------------------------------------------------

it('returns the monitor active monitor_contents by default', function () {
    $m = makeMonitor(['ip_address' => '10.0.0.1']);
    $c1 = makeContent(['content_name' => 'a.jpg', 'order' => 1]);
    $c2 = makeContent(['content_name' => 'b.jpg', 'order' => 2]);
    attachMonitorContent($m, $c1, ['content_order' => 1, 'display_time' => 5]);
    attachMonitorContent($m, $c2, ['content_order' => 2, 'display_time' => 15]);

    $playlist = $this->resolver->forMonitor('10.0.0.1', null);

    expect($playlist->items)->toHaveCount(2);
    expect($playlist->isMonitorContent)->toBeTrue();
    expect($playlist->bypassAllContent)->toBeFalse();
    expect($playlist->dailyMessage)->toBe('Welcome to IT');
    expect($playlist->items[0]->contentId)->toBe($c1->id);
    expect($playlist->items[0]->displayTime)->toBe(5);
    expect($playlist->items[1]->contentId)->toBe($c2->id);
    expect($playlist->items[1]->displayTime)->toBe(15);
});

it('excludes inactive monitor_contents', function () {
    $m = makeMonitor(['ip_address' => '10.0.0.1']);
    $c1 = makeContent();
    $c2 = makeContent();
    attachMonitorContent($m, $c1, ['active' => true]);
    attachMonitorContent($m, $c2, ['active' => false]);

    $playlist = $this->resolver->forMonitor('10.0.0.1', null);

    expect($playlist->items)->toHaveCount(1);
    expect($playlist->items[0]->contentId)->toBe($c1->id);
});

it('orders by content_order, then contents.order, then id', function () {
    $m = makeMonitor(['ip_address' => '10.0.0.1']);
    $c1 = makeContent(['order' => 5]);
    $c2 = makeContent(['order' => 1]);
    $c3 = makeContent(['order' => 3]);
    attachMonitorContent($m, $c1, ['content_order' => 2]);
    attachMonitorContent($m, $c2, ['content_order' => 1]);
    attachMonitorContent($m, $c3, ['content_order' => 1]);

    $playlist = $this->resolver->forMonitor('10.0.0.1', null);
    $ids = array_map(fn ($i) => $i->contentId, $playlist->items);

    expect($ids)->toBe([$c2->id, $c3->id, $c1->id]);
});

// -----------------------------------------------------------------------------
// Rule 2 — bypass (share)
// -----------------------------------------------------------------------------

it('returns share_contents when bypass is on and monitor is in share_monitors', function () {
    ApplicationSetting::current()->update(['bypass_all_content' => true]);

    $m = makeMonitor(['ip_address' => '10.0.0.1']);
    $c1 = makeContent(['content_name' => 'shared.jpg']);
    DB::table('share_monitors')->insert([
        'monitor_id' => $m->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('share_contents')->insert([
        'content_id' => $c1->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $playlist = $this->resolver->forMonitor('10.0.0.1', null);

    expect($playlist->items)->toHaveCount(1);
    expect($playlist->items[0]->contentId)->toBe($c1->id);
    expect($playlist->bypassAllContent)->toBeTrue();
    expect($playlist->isMonitorContent)->toBeFalse();
    expect($playlist->dailyMessage)->toBe('');
});

it('does not expand loopback variants in production (::1 monitor, 127.0.0.1 request)', function () {
    config(['signage.expand_loopback_variants' => false]);

    $m = makeMonitor(['ip_address' => '::1']);
    $c = makeContent(['content_name' => 'prod.jpg']);
    DB::table('monitor_contents')->insert([
        'monitor_id' => $m->id,
        'content_id' => $c->id,
        'active' => true,
        'content_order' => 0,
        'display_time' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($this->resolver->forMonitor('127.0.0.1', null)->isEmpty())->toBeTrue();
});

// -----------------------------------------------------------------------------
// Rule 3 — view
// -----------------------------------------------------------------------------

it('returns view_contents when the view belongs to the monitor', function () {
    $m = makeMonitor(['ip_address' => '10.0.0.1']);
    $view = View::create(['name' => 'morning']);
    $c1 = makeContent(['content_name' => 'v1.jpg']);
    $c2 = makeContent(['content_name' => 'v2.jpg']);

    linkViewToMonitor($m, $view);
    DB::table('view_contents')->insert([
        ['view_id' => $view->id, 'content_id' => $c1->id, 'created_at' => now(), 'updated_at' => now()],
        ['view_id' => $view->id, 'content_id' => $c2->id, 'created_at' => now(), 'updated_at' => now()],
    ]);
    attachMonitorContent($m, $c1, ['active' => true, 'display_time' => 7, 'content_order' => 1]);
    attachMonitorContent($m, $c2, ['active' => true, 'display_time' => 9, 'content_order' => 2]);

    $playlist = $this->resolver->forMonitor('10.0.0.1', 'morning');

    expect($playlist->items)->toHaveCount(2);
    expect($playlist->isMonitorContent)->toBeFalse();
    expect($playlist->items[0]->displayTime)->toBe(7);
    expect($playlist->items[1]->displayTime)->toBe(9);
});

it('view excludes contents whose monitor_contents is inactive', function () {
    $m = makeMonitor(['ip_address' => '10.0.0.1']);
    $view = View::create(['name' => 'morning']);
    $c1 = makeContent();
    $c2 = makeContent();

    linkViewToMonitor($m, $view);
    DB::table('view_contents')->insert([
        ['view_id' => $view->id, 'content_id' => $c1->id, 'created_at' => now(), 'updated_at' => now()],
        ['view_id' => $view->id, 'content_id' => $c2->id, 'created_at' => now(), 'updated_at' => now()],
    ]);
    attachMonitorContent($m, $c1, ['active' => true]);
    attachMonitorContent($m, $c2, ['active' => false]);

    $playlist = $this->resolver->forMonitor('10.0.0.1', 'morning');

    expect($playlist->items)->toHaveCount(1);
    expect($playlist->items[0]->contentId)->toBe($c1->id);
});

it('returns empty playlist when the view does not belong to this monitor', function () {
    $m = makeMonitor(['ip_address' => '10.0.0.1']);
    $otherM = makeMonitor(['ip_address' => '10.0.0.2']);
    $view = View::create(['name' => 'morning']);
    $c1 = makeContent();
    linkViewToMonitor($otherM, $view);   // linked to OTHER monitor
    DB::table('view_contents')->insert([
        'view_id' => $view->id,
        'content_id' => $c1->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    attachMonitorContent($m, $c1);

    $playlist = $this->resolver->forMonitor('10.0.0.1', 'morning');

    expect($playlist->isEmpty())->toBeTrue();
    expect($playlist->monitor)->toBeNull();
});

it('treats "undefined" and empty views as no view', function () {
    $m = makeMonitor(['ip_address' => '10.0.0.1']);
    $c1 = makeContent();
    attachMonitorContent($m, $c1);

    foreach ([null, '', 'undefined'] as $view) {
        $playlist = $this->resolver->forMonitor('10.0.0.1', $view);
        expect($playlist->isMonitorContent)->toBeTrue();
    }
});

// -----------------------------------------------------------------------------
// Rule 5 — base append
// -----------------------------------------------------------------------------

it('appends base_contents to the default playlist when active_base_content is on', function () {
    ApplicationSetting::current()->update(['active_base_content' => true]);

    $m = makeMonitor(['ip_address' => '10.0.0.1']);
    $mc = makeContent(['content_name' => 'own.jpg', 'order' => 1]);
    $bc = makeContent(['content_name' => 'base.jpg', 'order' => 2]);
    attachMonitorContent($m, $mc);

    DB::table('base_monitors')->insert([
        'monitor_id' => $m->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('base_contents')->insert([
        'content_id' => $bc->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $playlist = $this->resolver->forMonitor('10.0.0.1', null);

    expect($playlist->items)->toHaveCount(2);
    expect($playlist->items[0]->contentId)->toBe($mc->id);
    expect($playlist->items[1]->contentId)->toBe($bc->id);
});

it('does not append base when active_base_content is off', function () {
    $m = makeMonitor(['ip_address' => '10.0.0.1']);
    $mc = makeContent();
    $bc = makeContent();
    attachMonitorContent($m, $mc);

    DB::table('base_monitors')->insert([
        'monitor_id' => $m->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('base_contents')->insert([
        'content_id' => $bc->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $playlist = $this->resolver->forMonitor('10.0.0.1', null);

    expect($playlist->items)->toHaveCount(1);
});

// -----------------------------------------------------------------------------
// Rule 6 — filters
// -----------------------------------------------------------------------------

it('excludes deleted content even when to_date is in the future (fixed precedence, ADR-0002)', function () {
    $m = makeMonitor(['ip_address' => '10.0.0.1']);
    $ok = makeContent(['deleted' => false, 'to_date' => now()->addYear()->toDateString()]);
    $del = makeContent(['deleted' => true,  'to_date' => now()->addYear()->toDateString()]);
    attachMonitorContent($m, $ok, ['content_order' => 1]);
    attachMonitorContent($m, $del, ['content_order' => 2]);

    $playlist = $this->resolver->forMonitor('10.0.0.1', null);

    expect($playlist->items)->toHaveCount(1);
    expect($playlist->items[0]->contentId)->toBe($ok->id);
});

it('excludes content outside the from_date window', function () {
    $m = makeMonitor(['ip_address' => '10.0.0.1']);
    $future = makeContent(['from_date' => now()->addMonth()->toDateString()]);
    $past = makeContent(['from_date' => now()->subMonth()->toDateString()]);
    attachMonitorContent($m, $future);
    attachMonitorContent($m, $past);

    $playlist = $this->resolver->forMonitor('10.0.0.1', null);

    expect($playlist->items)->toHaveCount(1);
    expect($playlist->items[0]->contentId)->toBe($past->id);
});

it('excludes content outside the to_date window', function () {
    $m = makeMonitor(['ip_address' => '10.0.0.1']);
    $expired = makeContent(['to_date' => now()->subDay()->toDateString()]);
    $inWindow = makeContent(['to_date' => now()->addDay()->toDateString()]);
    attachMonitorContent($m, $expired, ['content_order' => 1]);
    attachMonitorContent($m, $inWindow, ['content_order' => 2]);

    $playlist = $this->resolver->forMonitor('10.0.0.1', null);

    expect($playlist->items)->toHaveCount(1);
    expect($playlist->items[0]->contentId)->toBe($inWindow->id);
});

// -----------------------------------------------------------------------------
// IP compatibility
// -----------------------------------------------------------------------------

it('resolves a monitor stored as ::1 when the request comes from 127.0.0.1', function () {

    config(['signage.expand_loopback_variants' => true]);

    $m = makeMonitor(['ip_address' => '::1']);
    $c = makeContent();
    attachMonitorContent($m, $c);

    $playlist = $this->resolver->forMonitor('127.0.0.1', null);

    expect($playlist->monitor)->not->toBeNull();
    expect($playlist->items)->toHaveCount(1);
});

// -----------------------------------------------------------------------------
// Empty playlist with a known monitor
// -----------------------------------------------------------------------------

it('returns an empty items list when a monitor has no contents', function () {
    $m = makeMonitor(['ip_address' => '10.0.0.1']);

    $playlist = $this->resolver->forMonitor('10.0.0.1', null);

    expect($playlist->isEmpty())->toBeTrue();
    expect($playlist->monitor)->not->toBeNull();
    expect($playlist->isMonitorContent)->toBeTrue();
    expect($playlist->dailyMessage)->toBe('Welcome to IT');
});

it('resolves the exact stored IP in production (no widening)', function () {
    config(['signage.expand_loopback_variants' => false]);

    $m = makeMonitor(['ip_address' => '127.0.0.1']);
    $c = makeContent(['content_name' => 'prod.jpg']);
    DB::table('monitor_contents')->insert([
        'monitor_id' => $m->id,
        'content_id' => $c->id,
        'active' => true,
        'content_order' => 0,
        'display_time' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $playlist = $this->resolver->forMonitor('127.0.0.1', null);

    expect($playlist->isEmpty())->toBeFalse()
        ->and($playlist->items)->toHaveCount(1)
        ->and($playlist->items[0]->contentName)->toBe('prod.jpg');
});

it('treats staging like production (no loopback variants)', function () {
    config(['signage.expand_loopback_variants' => false]);

    $m = makeMonitor(['ip_address' => '::1']);
    $c = makeContent(['content_name' => 'staging.jpg']);
    DB::table('monitor_contents')->insert([
        'monitor_id' => $m->id,
        'content_id' => $c->id,
        'active' => true,
        'content_order' => 0,
        'display_time' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $playlist = $this->resolver->forMonitor('127.0.0.1', null);

    expect($playlist->isEmpty())->toBeTrue();
});

it('matches a non-loopback IP in production', function () {
    config(['signage.expand_loopback_variants' => false]);

    $m = makeMonitor(['ip_address' => '10.0.0.1']);
    $c = makeContent(['content_name' => 'prod.jpg']);
    DB::table('monitor_contents')->insert([
        'monitor_id' => $m->id,
        'content_id' => $c->id,
        'active' => true,
        'content_order' => 0,
        'display_time' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $playlist = $this->resolver->forMonitor('10.0.0.1', null);

    expect($playlist->isEmpty())->toBeFalse()
        ->and($playlist->items)->toHaveCount(1);
});
