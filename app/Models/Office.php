<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
{
    /** @use HasFactory<\Database\Factories\OfficeFactory> */
    use HasFactory;

    protected $fillable = ["name", "city_id"];

    public function city(): BelongsTo
    {
        return $this->belongsTo("city");
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }



}
