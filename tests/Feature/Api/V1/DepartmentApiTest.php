<?php

use App\Models\City;
use App\Models\Department;
use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $city = City::factory()->create();
    $office = Office::create(['name' => 'HQ', 'city_id' => $city->id]);
    foreach (range(1, 12) as $i) {
        Department::create([
            'department_name' => sprintf('Dept %02d', $i),
            'office_id' => $office->id,
            'is_child' => false,
        ]);
    }
});

it('returns the legacy envelope', function () {
    $this->getJson('/api/v1/departments')->assertOk()->assertJsonStructure([
        'ResultCode', 'Message',
        'Data' => ['Items', 'TotalCount', 'PageIndex', 'RowNumber'],
    ]);
});

it('paginates correctly', function () {
    $r = $this->getJson('/api/v1/departments?pageIndex=1&rowNumber=10');
    expect($r->json('Data.Items'))->toHaveCount(10);
    expect($r->json('Data.TotalCount'))->toBe(12);
});

it('returns a flat list on /list', function () {
    expect($this->getJson('/api/v1/departments/list')->json('Data'))->toHaveCount(12);
});

it('searches across department_name', function () {
    $r = $this->getJson('/api/v1/departments?search=Dept 07');
    expect($r->json('Data.TotalCount'))->toBe(1);
});

it('sorts by DepartmentName desc', function () {
    $r = $this->getJson('/api/v1/departments?sortBy=DepartmentName&sortDirection=desc&rowNumber=2');
    expect(array_column($r->json('Data.Items'), 'DepartmentName'))
        ->toBe(['Dept 12', 'Dept 11']);
});

it('exposes expected PascalCase fields', function () {
    $r = $this->getJson('/api/v1/departments/list');
    expect($r->json('Data.0'))->toHaveKeys([
        'Id', 'DepartmentName', 'DailyMessage', 'IsChild',
        'Lock', 'ParentId', 'OfficeId',
    ]);
})->todo('validate against Step 9 legacy fixtures');
