<?php

namespace App\Models;

use Database\Factories\MonitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Monitor extends Model
{
    /** @use HasFactory<MonitorFactory> */
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

    public function shareMonitors(): HasMany
    {
        return $this->hasMany(ShareMonitor::class);
    }

    public function baseMonitors(): HasMany
    {
        return $this->hasMany(BaseMonitor::class);
    }

    public function views(): BelongsToMany
    {
        return $this->belongsToMany(View::class, 'monitor_views')
            ->withTimestamps();
    }
}
