<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarCategoryLabel extends Model
{
    protected $fillable = [
        'category_key',
        'label',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
