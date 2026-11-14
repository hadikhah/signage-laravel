<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'Id' => $this->id,

            'IpAddress' => $this->ip_address,

            'HostName' => $this->host_name,

            'DepartmentId' => $this->department_id,

            'MacAddress' => $this->mac_address,

            'Floor' => $this->floor,

            'Wing' => $this->wing,

            'Status' => $this->status,

            'Lock' => $this->lock,

            'Description' => $this->description,

            'Platform' => $this->platform,

            'Owner' => $this->owner,

        ];
    }
}
