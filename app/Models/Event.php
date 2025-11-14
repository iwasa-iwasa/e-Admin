<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Carbon\Carbon;

use App\Models\EventRecurrence;
use App\Models\EventAttachment;

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
        'is_all_day' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['rrule', 'duration'];

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

    /**
     * Get the recurrence rule for the event.
     */
    public function recurrence()
    {
        return $this->hasOne(EventRecurrence::class, 'event_id');
    }

    /**
     * Get the attachments for the event.
     */
    public function attachments()
    {
        return $this->hasMany(EventAttachment::class, 'event_id');
    }

    /**
     * Get the rrule object for FullCalendar.
     *
     * @return array|null
     */
    public function getRruleAttribute(): ?array
    {
        if (!$this->recurrence) {
            return null;
        }

        $rrule = [
            'freq' => $this->recurrence->recurrence_type,
            'interval' => $this->recurrence->recurrence_interval,
            'dtstart' => $this->start_date . 'T' . ($this->start_time ?? '00:00:00'),
            'until' => $this->recurrence->end_date ? $this->recurrence->end_date->format('Y-m-d') : null,
        ];

        if (!empty($this->recurrence->by_day)) {
            $rrule['byweekday'] = $this->recurrence->by_day;
        }

        if (!is_null($this->recurrence->by_set_pos)) {
            $rrule['bysetpos'] = $this->recurrence->by_set_pos;
        }

        return $rrule;
    }

    /**
     * Get the duration for FullCalendar recurring events.
     *
     * @return string|null
     */
    public function getDurationAttribute(): ?string
    {
        if (!$this->recurrence || $this->is_all_day || !$this->start_time || !$this->end_time) {
            return null;
        }

        try {
            $start = Carbon::parse($this->start_time);
            $end = Carbon::parse($this->end_time);
            $diff = $start->diff($end);
            return $diff->format('%H:%I:%S');
        } catch (\Exception $e) {
            return null;
        }
    }
}