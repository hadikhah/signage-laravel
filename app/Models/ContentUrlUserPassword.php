<?php

namespace App\Models;

use Database\Factories\ContentUrlUserPasswordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentUrlUserPassword extends Model
{
    /** @use HasFactory<ContentUrlUserPasswordFactory> */
    use HasFactory;

    protected $fillable = [
        'url',
        'user_name',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class, 'content_url_user_password_id');
    }
}
