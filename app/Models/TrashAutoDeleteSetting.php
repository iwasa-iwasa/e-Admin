<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrashAutoDeleteSetting extends Model
{
    protected $fillable = ['user_id', 'auto_delete_period'];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public static function getUserPeriod(int $userId): string
    {
        $setting = self::where('user_id', $userId)->first();
        return $setting ? $setting->auto_delete_period : 'disabled';
    }
    
    public static function setUserPeriod(int $userId, string $period): void
    {
        self::updateOrCreate(
            ['user_id' => $userId],
            ['auto_delete_period' => $period]
        );
    }
}
