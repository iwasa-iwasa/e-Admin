<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * @property string|null $reason
 * @property string|null $deactivated_at
 */
#[TypeScript]
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'department',
        'department_id',
        'role',
        'role_type',
        'is_active',
        'deactivated_at',
        'reason',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'email' => 'string',
        'email_verified_at' => 'datetime',
        'department' => 'string',
        'department_id' => 'integer',
        'role' => 'string',
        'role_type' => 'string',
        'is_active' => 'boolean',
        'deactivated_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the reminders for the user.
     */
    public function reminders()
    {
        return $this->hasMany(Reminder::class, 'user_id', 'id');
    }

    /**
     * Get the events created by the user.
     */
    public function events()
    {
        return $this->hasMany(Event::class, 'created_by', 'id');
    }

    /**
     * The events that the user is participating in.
     */
    public function participatingEvents()
    {
        return $this->belongsToMany(Event::class, 'event_participants', 'user_id', 'event_id');
    }

    /**
     * Get the notes written by the user.
     */
    public function sharedNotes()
    {
        return $this->hasMany(SharedNote::class, 'author_id', 'id');
    }

    /**
     * ユーザーがピン留めした共有ノートを取得します。
     */
    public function pinnedNotes(): BelongsToMany
    {
        return $this->belongsToMany(SharedNote::class, 'pinned_notes', 'user_id', 'note_id')->withTimestamps();
    }

    /**
     * Get the surveys created by the user.
     */
    public function surveys()
    {
        return $this->hasMany(Survey::class, 'created_by', 'id');
    }

    /**
     * Get the survey responses by the user.
     */
    public function surveyResponses()
    {
        return $this->hasMany(SurveyResponse::class, 'respondent_id', 'id');
    }
    /**
     * Get the logs for the user.
     */
    public function logs()
    {
        return $this->hasMany(UserLog::class)->orderBy('created_at', 'desc')->with('changer');
    }

    /**
     * Get the department that the user belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
