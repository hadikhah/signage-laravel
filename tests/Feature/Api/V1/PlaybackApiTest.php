<?php

use App\Models\ApplicationSetting;
use App\Models\City;
use App\Models\Content;
use App\Models\Department;
use App\Models\Monitor;
use App\Models\MonitorContent;
use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $city   = City::factory()->create();
    $office = Office::create(['name' => 'HQ', 'city_id' => $city->id]);
    $dept   = Department::create([
        'department_name' => 'IT',
        'office_id'       => $office->id,
        'is_child'        => false,
        'daily_message'   => 'Welcome to IT',
    ]);
    $this->dept = $dept;

    ApplicationSetting::query()->delete();
    ApplicationSetting::create([
        'id'                  => 1,
        'bypass_all_content'  => false,
        'active_base_content' => false,
        'last_update'         => null,
    ]);
});

function makeM(array $overrides = []): Monitor
{
    return Monitor::create(array_merge([
        'ip_address'    => '10.0.0.1',
        'department_id' => Department::first()->id,
        'status'        => 'online',
    ], $overrides));
}

function makeC(array $overrides = []): Content
{
    return Content::factory()->create(array_merge([
        'deleted'      => false,
        'order'        => 0,
        'content_type' => 1,
        'uploaded_at'  => now(),
    ], $overrides));
}

function linkMC(Monitor $m, Content $c, array $overrides = []): void
{
    MonitorContent::create(array_merge([
        'monitor_id'    => $m->id,
        'content_id'    => $c->id,
        'content_order' => 1,
        'display_time'  => 10,
        'active'        => true,
    ], $overrides));
}

// -----------------------------------------------------------------------------
// Envelope
// -----------------------------------------------------------------------------

it('returns ResultCode 4000 with the legacy envelope', function () {
    $m = makeM();
    $c = makeC();
    linkMC($m, $c);

    $r = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1');

    $r->assertOk()->assertJsonStructure([
        'ResultCode', 'Message',
        'Data' => [
            'ShareContents', 'MonitorContents', 'DailyMessage',
            'IsMonitorContent', 'ByPassAllContent', 'MonitorDateTime',
        ],
    ]);
    expect($r->json('ResultCode'))->toBe(4000);
    expect($r->json('Message'))->toBeNull();
});

it('serializes the content item with exact legacy field names', function () {
    $m = makeM();
    $c = makeC([
        'content_name'     => 'welcome.jpg',
        'client_file_name' => 'original.jpg',
        'content_type'     => 1,
        'uploaded_at'      => now(),
    ]);
    linkMC($m, $c, ['display_time' => 10]);

    $r = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1');

    $item = $r->json('Data.MonitorContents.0');

    // Exact casing matters — sample includes legacy typos.
    expect($item)->toHaveKeys([
        'ContentId', 'ContentName', 'UserId', 'UserName', 'ContentType',
        'Contentpath', 'ClientFileName', 'FromDate', 'ToDate',
        'ContentTypeText', 'UploadeDateTime', 'UploadDate', 'UploadTime',
        'DisplayTime', 'Active', 'UrlUserName', 'UrlPassword',
        'IsAutoLogin', 'Url',
    ]);

    expect($item['ContentId'])->toBe($c->id);
    expect($item['ContentName'])->toBe('welcome.jpg');
    expect($item['Contentpath'])->toBe("/media/files/{$c->id}/welcome.jpg");
    expect($item['ContentType'])->toBe(1);
    expect($item['ContentTypeText'])->toBe('عکس');
    expect($item['DisplayTime'])->toBe(10);
    expect($item['Active'])->toBeTrue();
    expect($item['UploadeDateTime'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/');
    expect($item['UploadDate'])->toMatch('/^\d{4}\/\d{2}\/\d{2}$/');
    expect($item['UploadTime'])->toMatch('/^\d{2}:\d{2}$/');
});

it('MonitorDateTime has the expected nested structure', function () {
    $m = makeM();
    $c = makeC();
    linkMC($m, $c);

    $dt = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1')
        ->json('Data.MonitorDateTime');

    expect($dt)->toHaveKeys([
        'TodayDate', 'PersianWeekDayText', 'WeekDayText', 'Full',
        'PersianMonth', 'PersianYear', 'PersianDay',
        'Month', 'Year', 'Day',
    ]);
    expect($dt['TodayDate'])->toMatch('/^\d{4}\/\d{2}\/\d{2}$/');
    expect($dt['Full'])->toContain('-');
    expect($dt['PersianYear'])->toBeInt()->toBeGreaterThan(1400);
    expect($dt['Year'])->toBeInt()->toBeGreaterThanOrEqual(2024);
});

it('DailyMessage is the monitor\'s department daily_message', function () {
    $m = makeM();
    $c = makeC();
    linkMC($m, $c);

    $r = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1');

    expect($r->json('Data.DailyMessage'))->toBe('Welcome to IT');
});

it('does not return a Contents key (client adds it)', function () {
    $m = makeM();
    $c = makeC();
    linkMC($m, $c);

    $data = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1')
        ->json('Data');

    expect($data)->not->toHaveKey('Contents');
});

// -----------------------------------------------------------------------------
// Paths — default / share / empty
// -----------------------------------------------------------------------------

it('default case: MonitorContents populated, ShareContents null, is_monitor_content true', function () {
    $m = makeM();
    $c = makeC();
    linkMC($m, $c);

    $r = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1');

    expect($r->json('Data.MonitorContents'))->toHaveCount(1);
    expect($r->json('Data.ShareContents'))->toBeNull();
    expect($r->json('Data.IsMonitorContent'))->toBeTrue();
    expect($r->json('Data.ByPassAllContent'))->toBeFalse();
});

it('share case: ShareContents populated, MonitorContents null, is_monitor_content false', function () {
    ApplicationSetting::current()->update(['bypass_all_content' => true]);

    $m  = makeM();
    $c  = makeC();
    DB::table('share_monitors')->insert([
        'monitor_id' => $m->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('share_contents')->insert([
        'content_id' => $c->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $r = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1');

    expect($r->json('Data.ShareContents'))->toHaveCount(1);
    expect($r->json('Data.MonitorContents'))->toBeNull();
    expect($r->json('Data.IsMonitorContent'))->toBeFalse();
    expect($r->json('Data.ByPassAllContent'))->toBeTrue();
    expect($r->json('Data.DailyMessage'))->toBe('');
});

it('unknown IP returns empty MonitorContents array with envelope intact', function () {
    $r = $this->getJson('/api/v1/playback/monitor-contents?ip=10.99.99.99');

    $r->assertOk();
    expect($r->json('ResultCode'))->toBe(4000);
    expect($r->json('Data.MonitorContents'))->toBeArray()->toBeEmpty();
    expect($r->json('Data.DailyMessage'))->toBe('');
});

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------

it('honors ?view= query', function () {
    $m    = makeM();
    $view = \App\Models\View::create(['name' => 'morning']);
    $c    = makeC();
    DB::table('monitor_views')->insert([
        'monitor_id' => $m->id, 'view_id' => $view->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('view_contents')->insert([
        'view_id' => $view->id, 'content_id' => $c->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    linkMC($m, $c);

    $r = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1&view=morning');

    expect($r->json('Data.MonitorContents'))->toHaveCount(1);
});

it('treats ?view=undefined as no view', function () {
    $m = makeM();
    $c = makeC();
    linkMC($m, $c);

    $r = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1&view=undefined');

    expect($r->json('Data.IsMonitorContent'))->toBeTrue();
});

// -----------------------------------------------------------------------------
// Legacy alias
// -----------------------------------------------------------------------------

it('serves the same response on the legacy alias /api/Content/MonitorContents', function () {
    $m = makeM();
    $c = makeC();
    linkMC($m, $c);

    $a = $this->getJson('/api/v1/playback/monitor-contents?ip=10.0.0.1')->json();
    $b = $this->getJson('/api/Content/MonitorContents?ip=10.0.0.1')->json();

    expect($b['ResultCode'])->toBe($a['ResultCode']);
    expect($b['Data']['MonitorContents'])->toEqual($a['Data']['MonitorContents']);
});