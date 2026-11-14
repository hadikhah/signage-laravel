<?php

namespace App\Models;

use Database\Factories\MonitorViewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorView extends Model
{
    /** @use HasFactory<MonitorViewFactory> */
    use HasFactory;

    protected $fillable = ['monitor_id', 'view_id'];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function view(): BelongsTo
    {
        return $this->belongsTo(View::class);
    }
}
