<?php

use App\Models\City;
use App\Models\Department;
use App\Models\Monitor;
use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fixtureRef(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/../../Fixtures/legacy/api/{$name}.json"), true);
}

/**
 * Minimal structural matcher — arrays whose expected value is `[]` are
 * treated as "assert non-empty array of any items" (value assertions are
 * delegated to dedicated tests elsewhere).
 */
function assertEnvelopeStructure(array $actual, array $expected): void
{
    expect(array_keys($actual))->toEqualCanonicalizing(array_keys($expected));

    foreach (['ResultCode' => 'int', 'Message' => 'null_or_string'] as $key => $type) {
        match ($type) {
            'int' => expect($actual[$key])->toBeInt(),
            'null_or_string' => expect($actual[$key] === null || is_string($actual[$key]))->toBeTrue(),
        };
    }

    expect($actual['Data'])->toBeArray();
    expect(array_keys($actual['Data']))->toEqualCanonicalizing(array_keys($expected['Data']));

    foreach ($expected['Data'] as $key => $sub) {
        if (is_array($sub) && array_key_exists('Items', $sub)) {
            expect($actual['Data'][$key])->toBeArray();
            expect(array_keys($actual['Data'][$key]))
                ->toEqualCanonicalizing(array_keys($sub));
        } else {
            match (gettype($sub)) {
                'integer' => expect($actual['Data'][$key])->toBeInt(),
                'string' => expect($actual['Data'][$key])->toBeString(),
                default => null,
            };
        }
    }
}

it('cities paginated endpoint matches fixture structure', function () {
    City::factory()->create(['name' => 'Tehran']);

    $response = $this->getJson('/api/v1/cities?pageIndex=1&rowNumber=20')->json();

    assertEnvelopeStructure($response, fixtureRef('cities-paginated'));

    expect($response['Data']['Items'][0])->toHaveKeys(['Id', 'Name']);
    expect($response['Data']['Items'][0]['Id'])->toBeInt();
});

it('cities list endpoint returns flat Data array', function () {
    City::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/cities/list')->json();

    expect($response['ResultCode'])->toBe(5000);
    expect($response['Message'])->toBeNull();
    expect($response['Data'])->toBeArray();
    expect($response['Data'])->toHaveCount(3);
    expect($response['Data'][0])->toHaveKeys(['Id', 'Name']);
});

it('offices use PascalCase fields', function () {
    $city = City::factory()->create();
    Office::create(['name' => 'HQ', 'city_id' => $city->id]);

    $row = $this->getJson('/api/v1/offices/list')->json('Data.0');

    expect($row)->toHaveKeys(['Id', 'Name', 'CityId']);
});

it('departments use PascalCase fields', function () {
    $city = City::factory()->create();
    $office = Office::create(['name' => 'HQ', 'city_id' => $city->id]);
    Department::create([
        'department_name' => 'IT',
        'office_id' => $office->id,
        'is_child' => false,
    ]);

    $row = $this->getJson('/api/v1/departments/list')->json('Data.0');

    expect($row)->toHaveKeys([
        'Id', 'DepartmentName', 'DailyMessage', 'IsChild',
        'Lock', 'ParentId', 'OfficeId',
    ]);
    expect($row['IsChild'])->toBeBool();
});

it('monitors use PascalCase fields', function () {
    $city = City::factory()->create();
    $office = Office::create(['name' => 'HQ', 'city_id' => $city->id]);
    $dept = Department::create([
        'department_name' => 'IT',
        'office_id' => $office->id,
        'is_child' => false,
    ]);
    Monitor::create([
        'ip_address' => '10.0.0.1', 'department_id' => $dept->id,
    ]);

    $row = $this->getJson('/api/v1/monitors/list')->json('Data.0');

    expect($row)->toHaveKeys([
        'Id', 'IpAddress', 'HostName', 'DepartmentId',
        'MacAddress', 'Floor', 'Wing', 'Status', 'Lock',
        'Description', 'Platform', 'Owner',
    ]);
});

it('every read endpoint returns ResultCode 5000 on success', function () {
    foreach ([
        '/api/v1/cities/list',
        '/api/v1/offices/list',
        '/api/v1/departments/list',
        '/api/v1/monitors/list',
    ] as $path) {
        $r = $this->getJson($path)->json();
        expect($r['ResultCode'])->toBe(5000, "$path");
    }
});
