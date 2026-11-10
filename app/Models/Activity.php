<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = ['type', 'title', 'description', 'performed_at'];

    protected $casts = [
        'performed_at' => 'datetime',
    ];
}
