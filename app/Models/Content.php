<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Content extends Model
{
    /** @use HasFactory<\Database\Factories\ContentFactory> */
    use HasFactory;
    protected $fillable = [
        'content_name',
        'client_file_name',
        'user_id',
        'content_type',
        'is_auto_login',
        'deleted',
        'order',
        'uploaded_at',
        'from_date',
        'to_date',
        'content_url_user_password_id',
    ];


    protected $casts = [
        'content_type' => 'integer',
        'is_auto_login' => 'boolean',
        'deleted' => 'boolean',
        'order' => 'integer',
        'uploaded_at' => 'datetime',
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contentUrlUserPassword()
    {
        return $this->belongsTo(ContentUrlUserPassword::class, 'content_url_user_password_id');
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class, 'monitor_contents')
            ->withPivot(['content_order', 'display_time', 'active'])
            ->withTimestamps();
    }

    public function viewContents(): HasMany
    {
        return $this->hasMany(ViewContent::class);
    }

    public function views(): BelongsToMany
    {
        return $this->belongsToMany(View::class, 'view_contents')
            ->withTimestamps();
    }

    public function shareContents(): HasMany
    {
        return $this->hasMany(ShareContent::class);
    }

    public function baseContents(): HasMany
    {
        return $this->hasMany(BaseContent::class);
    }


}
