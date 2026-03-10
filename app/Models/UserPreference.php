<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'show_company_calendar_in_trash',
        'notification_scope',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'show_company_calendar_in_trash' => 'boolean',
    ];

    /**
     * Get the user that owns the preferences.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
