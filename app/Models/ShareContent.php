<?php

namespace App\Models;

use Database\Factories\ShareContentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareContent extends Model
{
    /** @use HasFactory<ShareContentFactory> */
    use HasFactory;

    protected $fillable = ['content_id'];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
