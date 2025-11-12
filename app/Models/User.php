<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

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
}

