<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Monitor extends Model
{
    /** @use HasFactory<\Database\Factories\MonitorFactory> */
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'host_name',
        'mac_address',
        'department_id',
        'floor',
        'wing',
        'status',
        'lock',
        'description',
        'platform',
        'owner',
    ];

    // TODO $casts for 'status'. after figuring out what content it should hold 
 

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

}
