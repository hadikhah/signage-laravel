<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\HandlesPaginatedList;
use App\Http\Controllers\Controller;
use App\Http\Resources\MonitorResource;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Builder;

class MonitorController extends Controller
{
    use HandlesPaginatedList;

    protected function baseQuery(): Builder
    {
        return Monitor::query();
    }

    protected function resourceClass(): string
    {
        return MonitorResource::class;
    }

    protected function searchableColumns(): array
    {
        return ['ip_address', 'host_name', 'mac_address', 'description', 'platform', 'owner'];
    }

    protected function sortableColumns(): array
    {
        return [
            'Id' => 'id',
            'IpAddress' => 'ip_address',
            'HostName' => 'host_name',
            'DepartmentId' => 'department_id',
            'MacAddress' => 'mac_address',
            'Floor' => 'floor',
            'Wing' => 'wing',
            'Status' => 'status',
            'Platform' => 'platform',
            'Owner' => 'owner',
        ];
    }
}
