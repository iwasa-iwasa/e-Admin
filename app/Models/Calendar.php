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
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'calendar_id' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get the events for the calendar.
     */
    public function events()
    {
        return $this->hasMany(Event::class, 'calendar_id');
    }
}