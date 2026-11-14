<?php

namespace App\Models;

use Database\Factories\ViewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class View extends Model
{
    /** @use HasFactory<ViewFactory> */
    use HasFactory;

    protected $fillable = ['name'];

    public function viewContents(): HasMany
    {
        return $this->hasMany(ViewContent::class, 'view_id');
    }

    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'view_contents')
            ->withTimestamps();
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class, 'monitor_views')
            ->withTimestamps();
    }
}
