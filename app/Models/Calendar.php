<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

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
        'owner_type',
        'owner_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'calendar_id' => 'integer',
        'owner_id' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get the events for the calendar.
     */
    public function events()
    {
        return $this->hasMany(Event::class, 'calendar_id');
    }

    /**
     * Get the owner department (if owner_type is 'department').
     */
    public function ownerDepartment()
    {
        return $this->belongsTo(Department::class, 'owner_id');
    }

    /**
     * Get the shared events for this calendar.
     */
    public function sharedEvents()
    {
        return $this->belongsToMany(Event::class, 'calendar_event_shares', 'calendar_id', 'event_id', 'calendar_id', 'event_id');
    }
}