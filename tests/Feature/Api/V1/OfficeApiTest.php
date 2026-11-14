<?php

use App\Models\City;
use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $city = City::factory()->create();
    foreach (range(1, 15) as $i) {
        Office::create(['name' => sprintf('Office %02d', $i), 'city_id' => $city->id]);
    }
});

it('returns the legacy envelope', function () {
    $this->getJson('/api/v1/offices')->assertOk()->assertJsonStructure([
        'ResultCode',
        'Message',
        'Data' => ['Items', 'TotalCount', 'PageIndex', 'RowNumber'],
    ]);
});

it('paginates correctly', function () {
    $r = $this->getJson('/api/v1/offices?pageIndex=2&rowNumber=5');
    // dd($r->json());
    expect($r->json('Data.Items'))->toHaveCount(5);
    expect($r->json('Data.TotalCount'))->toBe(15);
    expect($r->json('Data.PageIndex'))->toBe(2);
});

it('returns a flat list on /list', function () {
    expect($this->getJson('/api/v1/offices/list')->json('Data'))->toHaveCount(15);
});

it('searches by name (case-insensitive)', function () {
    $r = $this->getJson('/api/v1/offices?search=office 05');
    expect($r->json('Data.TotalCount'))->toBe(1);
});

it('sorts by Name desc', function () {
    $r = $this->getJson('/api/v1/offices?sortBy=Name&sortDirection=desc&rowNumber=2');
    expect(array_column($r->json('Data.Items'), 'Name'))
        ->toBe(['Office 15', 'Office 14']);
});

it('exposes expected PascalCase fields', function () {
    $r = $this->getJson('/api/v1/offices/list');
    expect($r->json('Data.0'))->toHaveKeys(['Id', 'Name', 'CityId']);
})->todo('validate against Step 9 legacy fixtures');
