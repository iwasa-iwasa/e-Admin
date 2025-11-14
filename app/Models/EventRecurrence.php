<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class EventRecurrence extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'event_recurrence';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'recurrence_id';

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
        'event_id',
        'recurrence_type',
        'recurrence_interval',
        'by_day',
        'by_set_pos',
        'recurrence_unit',
        'end_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'end_date' => 'date',
        'by_day' => 'array',
    ];

    /**
     * Get the event that this recurrence belongs to.
     */
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
