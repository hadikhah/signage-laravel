<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\HandlesPaginatedList;
use App\Http\Controllers\Controller;
use App\Http\Resources\OfficeResource;
use App\Models\Office;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Override;

class OfficeController extends Controller
{
    use HandlesPaginatedList;

    public function baseQuery(): Builder
    {
        return Office::query();
    }

    public function resourceClass(): string
    {
        return OfficeResource::class;
    }

    public function searchableColumns(): array
    {
        return ["name"];
    }

    public function sortableColumns(): array
    {
        return [
            'Id'     => 'id',
            'Name'   => 'name',
            'CityId' => 'city_id',
        ];
    }
}
