<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrashAutoDeleteLog extends Model
{
    protected $fillable = ['user_id', 'period', 'deleted_count', 'executed_at'];
    
    protected $casts = [
        'executed_at' => 'datetime',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public static function createLog(int $userId, string $period, int $deletedCount): void
    {
        self::create([
            'user_id' => $userId,
            'period' => $period,
            'deleted_count' => $deletedCount,
            'executed_at' => now(),
        ]);
    }
}