<?php

namespace App\Models;

use Database\Factories\ApplicationSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationSetting extends Model
{
    /** @use HasFactory<ApplicationSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'bypass_all_content',
        'active_base_content',
        'last_update',
    ];

    protected $casts = [
        'bypass_all_content' => 'boolean',
        'active_base_content' => 'boolean',
        'last_update' => 'datetime',
    ];

    /**
     * Fetch the singleton settings row, creating it with defaults on
     * first call. There is exactly one row; use this instead of
     * ApplicationSetting::first() throughout the app.
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'bypass_all_content' => false,
            'active_base_content' => false,
        ]);
    }
}
