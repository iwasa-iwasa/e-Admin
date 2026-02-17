<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarEventShare extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'calendar_id',
        'event_id',
        'shared_by',
    ];

    protected $casts = [
        'id' => 'integer',
        'calendar_id' => 'integer',
        'event_id' => 'integer',
        'shared_by' => 'integer',
        'shared_at' => 'datetime',
    ];

    public function calendar()
    {
        return $this->belongsTo(Calendar::class, 'calendar_id', 'calendar_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'event_id');
    }

    public function sharedBy()
    {
        return $this->belongsTo(User::class, 'shared_by');
    }
}
