<?php

namespace App\Models;

use Database\Factories\FloorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Floor extends Model
{
    /** @use HasFactory<FloorFactory> */
    use HasFactory;
}
