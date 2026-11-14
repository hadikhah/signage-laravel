<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\HandlesPaginatedList;
use App\Http\Controllers\Controller;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    use HandlesPaginatedList;

    protected function baseQuery(): Builder
    {
        return Department::query();
    }
    protected function resourceClass(): string
    {
        return DepartmentResource::class;
    }

    protected function searchableColumns(): array
    {
        return ['department_name', 'daily_message'];
    }

    protected function sortableColumns(): array
    {
        return [
            'Id'             => 'id',
            'DepartmentName' => 'department_name',
            'IsChild'        => 'is_child',
            'OfficeId'       => 'office_id',
            'ParentId'       => 'parent_id',
        ];
    }
}
