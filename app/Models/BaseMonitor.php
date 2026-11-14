<?php

namespace App\Models;

use Database\Factories\BaseMonitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaseMonitor extends Model
{
    /** @use HasFactory<BaseMonitorFactory> */
    use HasFactory;

    protected $fillable = ['monitor_id'];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
