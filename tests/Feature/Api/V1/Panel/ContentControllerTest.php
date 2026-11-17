<?php

use App\Events\PlaylistRefreshEvent;
use App\Models\City;
use App\Models\Content;
use App\Models\Department;
use App\Models\Monitor;
use App\Models\MonitorContent;
use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('media');
    Event::fake([PlaylistRefreshEvent::class]);

    $city = City::factory()->create();
    $office = Office::create(['name' => 'HQ', 'city_id' => $city->id]);
    $dept = Department::create([
        'department_name' => 'IT',
        'office_id' => $office->id,
        'is_child' => false,
    ]);

    $this->monitor = Monitor::create([
        'ip_address' => '10.0.0.1',
        'department_id' => $dept->id,
        'status' => 'online',
    ]);
});

// =============================================================================
// Upload — image
// =============================================================================

it('uploads an image file, attaches to monitor, and activates it', function () {
    $file = UploadedFile::fake()->image('banner.jpg', 1920, 1080)->size(500);

    $r = $this->postJson('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => 1,
        'file' => $file,
        'display_time' => 10,
    ]);

    $r->assertOk();
    expect($r->json('ResultCode'))->toBe(5000);

    $content = Content::latest('id')->first();
    expect($content->content_type)->toBe(1);
    expect($content->client_file_name)->toBe('banner.jpg');
    expect($content->deleted)->toBeFalse();

    Storage::disk('media')->assertExists("files/{$content->id}/{$content->content_name}");

    $mc = MonitorContent::where('monitor_id', $this->monitor->id)
        ->where('content_id', $content->id)
        ->first();
    expect($mc)->not->toBeNull()->and($mc->active)->toBeTrue();
    expect($mc->display_time)->toBe(10);

    Event::assertDispatched(PlaylistRefreshEvent::class, fn ($e) => $e->ip === '10.0.0.1');
});

it('rejects upload for unknown IP with 404', function () {
    $file = UploadedFile::fake()->image('x.jpg');

    $r = $this->postJson('/api/v1/panel/contents?ip=10.99.99.99', [
        'content_type' => 1,
        'file' => $file,
        'display_time' => 10,
    ]);

    $r->assertStatus(404);
    expect(Content::count())->toBe(0);
});

it('rejects non-image file for image content type', function () {
    $file = UploadedFile::fake()->create('x.pdf', 100, 'application/pdf');

    $this->post('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => '1',
        'file' => $file,
        'display_time' => '10',
    ], ['Accept' => 'application/json'])->assertStatus(422);

    expect(Content::count())->toBe(0);
});

it('rejects files larger than 10MB', function () {
    $file = UploadedFile::fake()->image('big.jpg')->size(11 * 1024);

    $this->postJson('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => 1,
        'file' => $file,
        'display_time' => 10,
    ])->assertStatus(422);

    expect(Content::count())->toBe(0);
});

// =============================================================================
// Upload — URL
// =============================================================================

it('creates URL content with credentials', function () {
    $r = $this->postJson('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => 5,
        'url' => 'https://example.com',
        'url_user_name' => 'demo',
        'url_password' => 'secret',
        'display_time' => 60,
    ]);

    $r->assertOk();

    $content = Content::latest('id')->first();
    expect($content->content_type)->toBe(5);
    expect($content->is_auto_login)->toBeTrue();
    expect($content->contentUrlUserPassword->url)->toBe('https://example.com');
    expect($content->contentUrlUserPassword->user_name)->toBe('demo');

    $mc = MonitorContent::where('content_id', $content->id)->first();
    expect($mc->display_time)->toBe(60);

    Event::assertDispatched(PlaylistRefreshEvent::class);
});

it('creates URL content without credentials', function () {
    $r = $this->postJson('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => 5,
        'url' => 'https://example.com',
        'display_time' => 60,
    ]);

    $r->assertOk();

    $content = Content::latest('id')->first();
    expect($content->is_auto_login)->toBeFalse();
    expect($content->contentUrlUserPassword->user_name)->toBeNull();
});

it('rejects URL submission without url', function () {
    $this->postJson('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => 5,
        'display_time' => 60,
    ])->assertStatus(422);
});

it('rejects non-http/https URL schemes', function () {
    $this->postJson('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => 5,
        'url' => 'javascript:alert(1)',
        'display_time' => 60,
    ])->assertStatus(422);

    $this->postJson('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => 5,
        'url' => 'file:///etc/passwd',
        'display_time' => 60,
    ])->assertStatus(422);
});

// =============================================================================
// Display duration
// =============================================================================

it('uses the provided display_time for image uploads', function () {
    $file = UploadedFile::fake()->image('x.jpg');

    $this->post('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => '1',
        'file' => $file,
        'display_time' => '45',
    ], ['Accept' => 'application/json'])->assertOk();

    $content = Content::latest('id')->first();
    $mc = MonitorContent::where('content_id', $content->id)->first();

    expect($mc->display_time)->toBe(45);
});

it('uses the provided display_time for URL content', function () {
    $this->post('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => '5',
        'url' => 'https://example.com',
        'display_time' => '60',
    ], ['Accept' => 'application/json'])->assertOk();

    $content = Content::latest('id')->first();
    $mc = MonitorContent::where('content_id', $content->id)->first();

    expect($mc->display_time)->toBe(60);
});

it('rejects image upload without display_time', function () {
    $file = UploadedFile::fake()->image('x.jpg');

    $this->post('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => '1',
        'file' => $file,
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('rejects URL upload without display_time', function () {
    $this->post('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => '5',
        'url' => 'https://example.com',
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('rejects display_time above 300 seconds', function () {
    $file = UploadedFile::fake()->image('x.jpg');

    $this->post('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => '1',
        'file' => $file,
        'display_time' => '301',
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('rejects display_time below 1 second', function () {
    $file = UploadedFile::fake()->image('x.jpg');

    $this->post('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => '1',
        'file' => $file,
        'display_time' => '0',
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('ignores display_time when content type is video', function () {
    $file = UploadedFile::fake()->create('v.mp4', 500, 'video/mp4');

    $this->post('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => '2',
        'file' => $file,
        'display_time' => '30',
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('video uploads without display_time get display_time = 0', function () {
    $file = UploadedFile::fake()->create('v.mp4', 500, 'video/mp4');

    $this->post('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => '2',
        'file' => $file,
    ], ['Accept' => 'application/json'])->assertOk();

    $content = Content::latest('id')->first();
    $mc = MonitorContent::where('content_id', $content->id)->first();

    expect($mc->display_time)->toBe(0);
});

// =============================================================================
// Delete — single-monitor (orphan cleanup)
// =============================================================================

it('detaches and soft-deletes content used only by this monitor', function () {
    $c = Content::factory()->create(['deleted' => false, 'content_type' => 1]);
    MonitorContent::create([
        'monitor_id' => $this->monitor->id,
        'content_id' => $c->id,
        'content_order' => 1,
        'display_time' => 5,
        'active' => true,
    ]);
    Storage::disk('media')->put("files/{$c->id}/{$c->content_name}", 'x');

    $r = $this->deleteJson("/api/v1/panel/contents/{$c->id}?ip=10.0.0.1");

    $r->assertOk();
    expect($r->json('Data.detached'))->toBeTrue();
    expect($r->json('Data.deleted'))->toBeTrue();

    expect($c->fresh()->deleted)->toBeTrue();
    expect(MonitorContent::where('content_id', $c->id)->exists())->toBeFalse();
    Storage::disk('media')->assertMissing("files/{$c->id}/{$c->content_name}");

    Event::assertDispatched(PlaylistRefreshEvent::class);
});

// =============================================================================
// Delete — shared content (no soft-delete)
// =============================================================================

it('detaches from one monitor without deleting when content is still used elsewhere', function () {
    $city = City::factory()->create();
    $office = Office::create(['name' => 'HQ2', 'city_id' => $city->id]);
    $dept = Department::create([
        'department_name' => 'Other',
        'office_id' => $office->id,
        'is_child' => false,
    ]);
    $other = Monitor::create([
        'ip_address' => '10.0.0.2',
        'department_id' => $dept->id,
    ]);

    $c = Content::factory()->create(['deleted' => false, 'content_type' => 1]);
    MonitorContent::create([
        'monitor_id' => $this->monitor->id,
        'content_id' => $c->id,
        'content_order' => 1,
        'display_time' => 5,
        'active' => true,
    ]);
    MonitorContent::create([
        'monitor_id' => $other->id,
        'content_id' => $c->id,
        'content_order' => 1,
        'display_time' => 5,
        'active' => true,
    ]);
    Storage::disk('media')->put("files/{$c->id}/{$c->content_name}", 'x');

    $r = $this->deleteJson("/api/v1/panel/contents/{$c->id}?ip=10.0.0.1");

    $r->assertOk();
    expect($r->json('Data.detached'))->toBeTrue();
    expect($r->json('Data.deleted'))->toBeFalse();

    expect(MonitorContent::where('monitor_id', $this->monitor->id)->where('content_id', $c->id)->exists())->toBeFalse();
    expect(MonitorContent::where('monitor_id', $other->id)->where('content_id', $c->id)->exists())->toBeTrue();
    expect($c->fresh()->deleted)->toBeFalse();
    Storage::disk('media')->assertExists("files/{$c->id}/{$c->content_name}");
});

// =============================================================================
// Delete — ownership rejection
// =============================================================================

it('rejects delete of content belonging to a different monitor with 403', function () {
    $city = City::factory()->create();
    $office = Office::create(['name' => 'HQ2', 'city_id' => $city->id]);
    $dept = Department::create([
        'department_name' => 'Other',
        'office_id' => $office->id,
        'is_child' => false,
    ]);
    $other = Monitor::create(['ip_address' => '10.0.0.2', 'department_id' => $dept->id]);

    $c = Content::factory()->create(['deleted' => false]);
    MonitorContent::create([
        'monitor_id' => $other->id,
        'content_id' => $c->id,
        'content_order' => 1,
        'display_time' => 5,
        'active' => true,
    ]);

    $this->deleteJson("/api/v1/panel/contents/{$c->id}?ip=10.0.0.1")
        ->assertStatus(403);

    expect($c->fresh()->deleted)->toBeFalse();
});

it('rejects delete for unknown IP with 404', function () {
    $c = Content::factory()->create(['deleted' => false]);

    $this->deleteJson("/api/v1/panel/contents/{$c->id}?ip=10.99.99.99")
        ->assertStatus(404);
});

// =============================================================================
// Ordering
// =============================================================================

it('assigns sequential content_order without gaps', function () {
    foreach ([1, 2, 3] as $i) {
        $this->postJson('/api/v1/panel/contents?ip=10.0.0.1', [
            'content_type' => 5,
            'url' => "https://example{$i}.com",
            'display_time' => 10,
        ])->assertOk();
    }

    $orders = MonitorContent::where('monitor_id', $this->monitor->id)
        ->orderBy('content_order')
        ->pluck('content_order')
        ->all();

    expect($orders)->toBe([1, 2, 3]);
});

// =============================================================================
// Multipart form-data (browser-style, string content_type)
// =============================================================================

it('accepts URL submission via multipart form data (string content_type)', function () {
    $r = $this->post('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => '5',
        'url' => 'https://google.com',
        'display_time' => 60,
    ], ['Accept' => 'application/json']);

    $r->assertOk();
    expect(Content::latest('id')->first()->content_type)->toBe(5);
});

it('accepts image upload via multipart form data (string content_type)', function () {
    $file = UploadedFile::fake()->image('banner.jpg');

    $r = $this->post('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => '1',
        'file' => $file,
        'display_time' => 10,
    ], ['Accept' => 'application/json']);

    $r->assertOk();
    expect(Content::latest('id')->first()->content_type)->toBe(1);
});

// =============================================================================
// Edge — invalid content_type must not 500
// =============================================================================

it('rejects non-numeric content_type with 422 (not 500)', function () {
    $this->post('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => 'abc',
        'url' => 'https://example.com',
        'display_time' => '60',
    ], ['Accept' => 'application/json'])->assertStatus(422);
});

it('rejects array content_type with 422 (not 500)', function () {
    $this->post('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => ['5'],
        'url' => 'https://example.com',
        'display_time' => '60',
    ], ['Accept' => 'application/json'])->assertStatus(422);
});
