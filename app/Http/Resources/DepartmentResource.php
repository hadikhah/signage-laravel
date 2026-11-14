<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [

            "Id" => $this->id,

            "DepartmentName" => $this->department_name,

            "DailyMessage" => $this->daily_message,

            "IsChild" => (bool)$this->is_child,

            "lock" => $this->lock,

            "PartentId" => $this->partent_id,

            "OfficeId" => $this->office_id

        ];
    }
}
