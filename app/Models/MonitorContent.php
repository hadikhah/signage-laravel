<?php

namespace App\Models;

use Database\Factories\MonitorContentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorContent extends Model
{
    /** @use HasFactory<MonitorContentFactory> */
    use HasFactory;

    protected $fillable = [
        'monitor_id',
        'content_id',
        'content_order',
        'display_time',
        'active',
    ];

    protected $casts = [
        'content_order' => 'integer',
        'display_time' => 'integer',   // TODO: units confirmed?
        'active' => 'boolean',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
