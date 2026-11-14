<?php

namespace App\Models;

use Database\Factories\BaseContentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaseContent extends Model
{
    /** @use HasFactory<BaseContentFactory> */
    use HasFactory;

    protected $fillable = ['content_id'];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
