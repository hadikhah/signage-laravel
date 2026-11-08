<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    /** @use HasFactory<\Database\Factories\DepartmentFactory> */
    use HasFactory;

    protected $fillable = [
        'department_name',
        'daily_message',
        'lock',
        'parent_id',
        'office_id',
        'is_child',
    ];

    protected $casts = [
        'is_child' => 'boolean',
    ];


    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class);
    }



}
