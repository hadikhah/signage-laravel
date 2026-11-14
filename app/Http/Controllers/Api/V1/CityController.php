<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\HandlesPaginatedList;
use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Database\Eloquent\Builder;

class CityController extends Controller
{
    use HandlesPaginatedList;

    protected function baseQuery(): Builder
    {
        return City::query();
    }
    protected function resourceClass(): string
    {
        return CityResource::class;
    }

    protected function searchableColumns(): array
    {
        return ['name'];
    }

    protected function sortableColumns(): array
    {
        return [
            'Id'   => 'id',
            'Name' => 'name',
        ];
    }
}
