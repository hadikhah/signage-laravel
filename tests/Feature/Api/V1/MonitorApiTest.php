<?php

use App\Models\City;
use App\Models\Department;
use App\Models\Monitor;
use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $city = City::factory()->create();
    $office = Office::create(['name' => 'HQ', 'city_id' => $city->id]);
    $dept = Department::create([
        'department_name' => 'IT',
        'office_id' => $office->id,
        'is_child' => false,
    ]);

    foreach (range(1, 8) as $i) {
        Monitor::create([
            'ip_address' => "10.0.0.{$i}",
            'department_id' => $dept->id,
            'status' => 'online',
            'host_name' => "host-{$i}",
        ]);
    }
});

it('returns the legacy envelope', function () {
    $this->getJson('/api/v1/monitors')->assertOk()->assertJsonStructure([
        'ResultCode',
        'Message',
        'Data' => ['Items', 'TotalCount', 'PageIndex', 'RowNumber'],
    ]);
});

it('paginates correctly', function () {
    $r = $this->getJson('/api/v1/monitors?pageIndex=1&rowNumber=5');
    expect($r->json('Data.Items'))->toHaveCount(5);
    expect($r->json('Data.TotalCount'))->toBe(8);
});

it('returns a flat list on /list', function () {
    expect($this->getJson('/api/v1/monitors/list')->json('Data'))->toHaveCount(8);
});

it('searches across ip_address and host_name', function () {
    expect($this->getJson('/api/v1/monitors?search=10.0.0.3')->json('Data.TotalCount'))->toBe(1);
    expect($this->getJson('/api/v1/monitors?search=host-5')->json('Data.TotalCount'))->toBe(1);
});

it('sorts by IpAddress desc', function () {
    $r = $this->getJson('/api/v1/monitors?sortBy=IpAddress&sortDirection=desc&rowNumber=2');
    expect(array_column($r->json('Data.Items'), 'IpAddress'))
        ->toBe(['10.0.0.8', '10.0.0.7']);
});

it('sorts by HostName asc', function () {
    $r = $this->getJson('/api/v1/monitors?sortBy=HostName&rowNumber=3');
    expect(array_column($r->json('Data.Items'), 'HostName'))
        ->toBe(['host-1', 'host-2', 'host-3']);
});

it('exposes expected PascalCase fields', function () {
    $r = $this->getJson('/api/v1/monitors/list');
    expect($r->json('Data.0'))->toHaveKeys([
        'Id',
        'IpAddress',
        'HostName',
        'DepartmentId',
        'MacAddress',
        'Floor',
        'Wing',
        'Status',
        'Lock',
        'Description',
        'Platform',
        'Owner',
    ]);
})->todo('validate against Step 9 legacy fixtures');
