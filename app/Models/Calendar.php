<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Calendar extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'calendars';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'calendar_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'calendar_name',
        'calendar_type',
        'owner_id',
    ];

    /**
     * Get the user that owns the calendar.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the events for the calendar.
     */
    public function events()
    {
        return $this->hasMany(Event::class, 'calendar_id');
    }
}