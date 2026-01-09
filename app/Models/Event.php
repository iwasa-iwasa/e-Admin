<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Carbon\Carbon;

use App\Models\EventRecurrence;
use App\Models\EventAttachment;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORY_MEETING = '会議';
    public const CATEGORY_WORK = '業務';
    public const CATEGORY_VISITOR = '来客';
    public const CATEGORY_BUSINESS_TRIP = '出張';
    public const CATEGORY_VACATION = '休暇';
    public const CATEGORY_OTHER = 'その他';

    public const IMPORTANCE_HIGH = '重要';
    public const IMPORTANCE_MEDIUM = '中';
    public const IMPORTANCE_LOW = '低';

    public const COLOR_BLUE = 'blue';
    public const COLOR_GREEN = 'green';
    public const COLOR_YELLOW = 'yellow';
    public const COLOR_PURPLE = 'purple';
    public const COLOR_PINK = 'pink';
    public const COLOR_GRAY = 'gray';

    public const CATEGORY_COLORS = [
        self::CATEGORY_MEETING => self::COLOR_BLUE,
        self::CATEGORY_WORK => self::COLOR_GREEN,
        self::CATEGORY_VISITOR => self::COLOR_YELLOW,
        self::CATEGORY_BUSINESS_TRIP => self::COLOR_PURPLE,
        self::CATEGORY_VACATION => self::COLOR_PINK,
        self::CATEGORY_OTHER => self::COLOR_GRAY,
    ];

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
        'progress',
        'deadline_date',
        'deadline_time',
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
        'event_id' => 'integer',
        'calendar_id' => 'integer',
        'start_date' => 'date:Y-m-d',
        'start_time' => 'datetime:H:i',
        'end_date' => 'date:Y-m-d',
        'end_time' => 'datetime:H:i',
        'is_all_day' => 'boolean',
        'created_by' => 'integer',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
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

        // Format dtstart with UTC timezone to avoid timezone conversion issues
        $startDate = $this->start_date instanceof Carbon 
            ? $this->start_date->toDateString() 
            : (string) $this->start_date;
        $startTime = $this->start_time ?? '00:00:00';
        $dtstart = $startDate . 'T' . $startTime . 'Z'; // Add 'Z' to indicate UTC
        
        $rrule = [
            'freq' => $this->recurrence->recurrence_type,
            'interval' => $this->recurrence->recurrence_interval,
            'dtstart' => $dtstart,
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