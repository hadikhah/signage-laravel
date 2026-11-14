<?php

use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (range(1, 25) as $i) {
        City::create(['name' => sprintf('City %02d', $i)]);
    }
});

// ---- Envelope --------------------------------------------------------------
it('returns the legacy envelope on the paginated endpoint', function () {
    $r = $this->getJson('/api/v1/cities');
    $r->assertOk()->assertJsonStructure([
        'ResultCode',
        'Message',
        'Data' => ['Items', 'TotalCount', 'PageIndex', 'RowNumber'],
    ]);
    expect($r->json('ResultCode'))->toBe(5000);
    expect($r->json('Message'))->toBeNull();
});

it('returns the legacy envelope on /list', function () {
    $r = $this->getJson('/api/v1/cities/list');
    $r->assertOk()->assertJsonStructure(['ResultCode', 'Message', 'Data']);
    expect($r->json('ResultCode'))->toBe(5000);
    expect($r->json('Message'))->toBeNull();
    expect($r->json('Data'))->toBeArray();
});

// ---- Pagination ------------------------------------------------------------
it('defaults to pageIndex=1, rowNumber=20', function () {
    $r = $this->getJson('/api/v1/cities');
    expect($r->json('Data.PageIndex'))->toBe(1);
    expect($r->json('Data.RowNumber'))->toBe(20);
    expect($r->json('Data.Items'))->toHaveCount(20);
    expect($r->json('Data.TotalCount'))->toBe(25);
});

it('honors explicit pagination params', function () {
    $r = $this->getJson('/api/v1/cities?pageIndex=2&rowNumber=10');
    expect($r->json('Data.PageIndex'))->toBe(2);
    expect($r->json('Data.RowNumber'))->toBe(10);
    expect($r->json('Data.Items'))->toHaveCount(10);
    expect($r->json('Data.TotalCount'))->toBe(25);
});

it('returns an empty Items array past the last page', function () {
    $r = $this->getJson('/api/v1/cities?pageIndex=99&rowNumber=10');
    expect($r->json('Data.Items'))->toBeArray()->toBeEmpty();
    expect($r->json('Data.TotalCount'))->toBe(25);
});

it('rejects negative pageIndex', function () {
    $this->getJson('/api/v1/cities?pageIndex=-1')->assertStatus(422);
});

it('rejects rowNumber above the cap', function () {
    $this->getJson('/api/v1/cities?rowNumber=9999')->assertStatus(422);
});

// ---- Search ----------------------------------------------------------------
it('filters paginated results by search term', function () {
    $r = $this->getJson('/api/v1/cities?search=City 05');
    // dd($r->json());
    expect($r->json('Data.TotalCount'))->toBe(1);
    expect($r->json('Data.Items.0.Name'))->toBe('City 05');
});

it('search is case-insensitive', function () {
    $r = $this->getJson('/api/v1/cities?search=city 05');
    expect($r->json('Data.TotalCount'))->toBe(1);
});

it('empty search returns all rows', function () {
    $r = $this->getJson('/api/v1/cities?search=');
    expect($r->json('Data.TotalCount'))->toBe(25);
});

it('search works on /list too', function () {
    $r = $this->getJson('/api/v1/cities/list?search=City 1');
    expect($r->json('Data'))->toHaveCount(10); // City 10..19
});

it('search combines with pagination', function () {
    $r = $this->getJson('/api/v1/cities?search=City 1&pageIndex=2&rowNumber=3');
    expect($r->json('Data.TotalCount'))->toBe(10);
    expect($r->json('Data.Items'))->toHaveCount(3);
});

// ---- Sort ------------------------------------------------------------------
it('sorts by Name ascending', function () {
    $r = $this->getJson('/api/v1/cities?sortBy=Name&rowNumber=3');
    expect(array_column($r->json('Data.Items'), 'Name'))
        ->toBe(['City 01', 'City 02', 'City 03']);
});

it('sorts descending when sortDirection=desc', function () {
    $r = $this->getJson('/api/v1/cities?sortBy=Name&sortDirection=desc&rowNumber=3');
    expect(array_column($r->json('Data.Items'), 'Name'))
        ->toBe(['City 25', 'City 24', 'City 23']);
});

it('falls back to id sort on unknown sortBy', function () {
    $r = $this->getJson('/api/v1/cities?sortBy=DoesNotExist&rowNumber=3');
    // dd($r->json());
    expect(array_column($r->json('Data.Items'), 'Name'))
        ->toBe(['City 01', 'City 02', 'City 03']);
});

it('sort works on /list too', function () {
    $r = $this->getJson('/api/v1/cities/list?sortBy=Name&sortDirection=desc');
    expect($r->json('Data.0.Name'))->toBe('City 25');
});

it('search + sort combine', function () {
    $r = $this->getJson('/api/v1/cities?search=City 1&sortBy=Name&sortDirection=desc&rowNumber=3');
    expect($r->json('Data.TotalCount'))->toBe(10);
    expect(array_column($r->json('Data.Items'), 'Name'))
        ->toBe(['City 19', 'City 18', 'City 17']);
});

// ---- /list flat shape ------------------------------------------------------
it('returns all rows on /list with no pagination wrapper', function () {
    $r = $this->getJson('/api/v1/cities/list');
    expect($r->json('Data'))->toHaveCount(25);
    expect($r->json('Data.Items'))->toBeNull();
});

// ---- Provisional field names ----------------------------------------------
it('exposes PascalCase Id and Name', function () {
    $r = $this->getJson('/api/v1/cities/list');
    expect($r->json('Data.0'))->toHaveKeys(['Id', 'Name']);
})->todo('validate against Step 9 legacy fixtures');
