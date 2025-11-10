<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class Event extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'events';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'event_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'calendar_id',
        'title',
        'description',
        'location',
        'url',
        'category',
        'importance',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
        'is_all_day',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_all_day' => 'boolean',
    ];

    /**
     * Get the user who created the event.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the calendar that this event belongs to.
     */
    public function calendar()
    {
        return $this->belongsTo(Calendar::class, 'calendar_id');
    }

    /**
     * The participants that belong to the event.
     */
    public function participants()
    {
        return $this->belongsToMany(User::class, 'event_participants', 'event_id', 'user_id');
    }
}