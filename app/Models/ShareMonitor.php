<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareMonitor extends Model
{
    /** @use HasFactory<\Database\Factories\ShareMonitorFactory> */
    use HasFactory;

    protected $fillable = ['monitor_id'];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
