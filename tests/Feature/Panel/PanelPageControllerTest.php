<?php

use App\Models\City;
use App\Models\Content;
use App\Models\Department;
use App\Models\Monitor;
use App\Models\MonitorContent;
use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $city = City::factory()->create();
    $office = Office::create(['name' => 'HQ', 'city_id' => $city->id]);
    $dept = Department::create([
        'department_name' => 'IT',
        'office_id' => $office->id,
        'is_child' => false,
    ]);

    $this->monitor = Monitor::create([
        'ip_address' => '10.0.0.1',
        'host_name' => 'reception-01',
        'department_id' => $dept->id,
        'status' => 'online',
    ]);

    $this->dept = $dept;
});

// =============================================================================
// Happy path — known IP
// =============================================================================

it('renders the panel page for a known IP', function () {
    $this->get('/panel?ip=10.0.0.1')
        ->assertOk()
        ->assertSee('10.0.0.1')
        ->assertSee('reception-01');
});

it('does not render the no-monitor state when the IP matches', function () {
    $this->get('/panel?ip=10.0.0.1')
        ->assertOk()
        ->assertDontSee('No monitor registered');
});

// =============================================================================
// Unknown IP
// =============================================================================

it('renders no-monitor state for unknown IP', function () {
    $this->get('/panel?ip=10.99.99.99')
        ->assertOk()
        ->assertSee('No monitor registered')
        ->assertSee('10.99.99.99');
});

// =============================================================================
// Content listing
// =============================================================================

it('lists contents attached to the monitor', function () {
    $a = Content::factory()->create([
        'content_name' => 'uuid-a.jpg',
        'client_file_name' => 'alpha.jpg',
        'deleted' => false,
        'content_type' => 1,
    ]);
    $b = Content::factory()->create([
        'content_name' => 'uuid-b.jpg',
        'client_file_name' => 'beta.jpg',
        'deleted' => false,
        'content_type' => 1,
    ]);

    MonitorContent::create([
        'monitor_id' => $this->monitor->id,
        'content_id' => $a->id,
        'content_order' => 1,
        'display_time' => 5,
        'active' => true,
    ]);
    MonitorContent::create([
        'monitor_id' => $this->monitor->id,
        'content_id' => $b->id,
        'content_order' => 2,
        'display_time' => 5,
        'active' => true,
    ]);

    $this->get('/panel?ip=10.0.0.1')
        ->assertOk()
        ->assertSee('alpha.jpg')
        ->assertSee('beta.jpg');
});

it('does not list soft-deleted contents', function () {
    $visible = Content::factory()->create([
        'client_file_name' => 'visible.jpg',
        'deleted' => false,
    ]);
    $gone = Content::factory()->create([
        'client_file_name' => 'hidden-deleted.jpg',
        'deleted' => true,
    ]);

    MonitorContent::create([
        'monitor_id' => $this->monitor->id,
        'content_id' => $visible->id,
        'content_order' => 1,
        'display_time' => 5,
        'active' => true,
    ]);
    MonitorContent::create([
        'monitor_id' => $this->monitor->id,
        'content_id' => $gone->id,
        'content_order' => 2,
        'display_time' => 5,
        'active' => true,
    ]);

    $this->get('/panel?ip=10.0.0.1')
        ->assertOk()
        ->assertSee('visible.jpg')
        ->assertDontSee('hidden-deleted.jpg');
});

it('does not list contents attached to a different monitor', function () {
    // Second monitor
    $city2 = City::factory()->create();
    $office2 = Office::create(['name' => 'HQ2', 'city_id' => $city2->id]);
    $dept2 = Department::create([
        'department_name' => 'Other',
        'office_id' => $office2->id,
        'is_child' => false,
    ]);
    $other = Monitor::create([
        'ip_address' => '10.0.0.2',
        'department_id' => $dept2->id,
    ]);

    $mine = Content::factory()->create([
        'client_file_name' => 'mine.jpg',
        'deleted' => false,
    ]);
    $theirs = Content::factory()->create([
        'client_file_name' => 'their-content.jpg',
        'deleted' => false,
    ]);

    MonitorContent::create([
        'monitor_id' => $this->monitor->id,
        'content_id' => $mine->id,
        'content_order' => 1,
        'display_time' => 5,
        'active' => true,
    ]);
    MonitorContent::create([
        'monitor_id' => $other->id,
        'content_id' => $theirs->id,
        'content_order' => 1,
        'display_time' => 5,
        'active' => true,
    ]);

    $this->get('/panel?ip=10.0.0.1')
        ->assertOk()
        ->assertSee('mine.jpg')
        ->assertDontSee('their-content.jpg');
});

it('shows the empty state when the monitor has no contents', function () {
    $this->get('/panel?ip=10.0.0.1')
        ->assertOk()
        ->assertSee('No contents yet');
});

// =============================================================================
// Dev override
// =============================================================================

it('honors the ?ip= dev override', function () {
    // Caller IP is 127.0.0.1 (test harness) but ?ip=10.0.0.1 should target
    // the real monitor
    $this->get('/panel?ip=10.0.0.1')
        ->assertOk()
        ->assertSee('reception-01');
});

it('shows the dev hint in local/testing environments', function () {
    // Default test env is 'testing' → devOverride = true
    $this->get('/panel?ip=10.99.99.99')
        ->assertOk()
        ->assertSee('Dev tip')
        ->assertSee('?ip=');
});

it('accepts URL submission via multipart form data (string content_type)', function () {
    // Simulates browser FormData — content_type arrives as string "5"
    $r = $this->post('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => '5',
        'url' => 'https://google.com',
    ], ['Accept' => 'application/json']);

    $r->assertOk();
    expect(Content::latest('id')->first()->content_type)->toBe(5);
});

it('accepts image upload via multipart form data (string content_type)', function () {
    $file = UploadedFile::fake()->image('banner.jpg');

    $r = $this->post('/api/v1/panel/contents?ip=10.0.0.1', [
        'content_type' => '1',
        'file' => $file,
    ], ['Accept' => 'application/json']);

    $r->assertOk();
    expect(Content::latest('id')->first()->content_type)->toBe(1);
});
