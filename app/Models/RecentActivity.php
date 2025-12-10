<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecentActivity extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'user_id',
        'item_type',
        'item_id',
        'accessed_at',
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
    ];

    public static function track($userId, $itemType, $itemId)
    {
        static::updateOrCreate(
            [
                'user_id' => $userId,
                'item_type' => $itemType,
                'item_id' => $itemId,
            ],
            [
                'accessed_at' => now(),
            ]
        );
    }
}
