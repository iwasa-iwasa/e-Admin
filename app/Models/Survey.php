<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;


#[TypeScript]
class Survey extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @property int $survey_id
     * @property string $title
     * @property string|null $description
     * @property int $created_by
     * @property string|null $deadline_date
     * @property string|null $deadline_time
     * @property boolean $is_active
     * @property boolean $is_deleted
     * @property string|null $deleted_at
     * @property int|null $version
     * @property string|null $created_at
     * @property string|null $updated_at
     * @property-read \App\Models\User|null $creator
     * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\SurveyQuestion[] $questions
     * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\SurveyResponse[] $responses
     * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $designatedUsers
     * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $respondents
     * 
     * @var string
     */
    protected $table = 'surveys';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'survey_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'categories',
        'created_by',
        'deadline_date',
        'deadline_time',
        'is_active',
        'is_deleted',
        'deleted_at',
        'version',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'survey_id' => 'integer',
        'created_by' => 'integer',
        'deadline_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
        'categories' => 'array',
    ];

    /**
     * Get the user who created the survey.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the questions for the survey.
     */
    public function questions()
    {
        return $this->hasMany(SurveyQuestion::class, 'survey_id');
    }

    /**
     * Get the responses for the survey.
     */
    public function responses()
    {
        return $this->hasMany(SurveyResponse::class, 'survey_id', 'survey_id');
    }

    /**
     * Get the designated respondents for the survey.
     */
    public function respondents()
    {
        return $this->hasMany(SurveyRespondent::class, 'survey_id', 'survey_id');
    }

    /**
     * Get the users who are designated to respond to this survey.
     */
    public function designatedUsers()
    {
        return $this->belongsToMany(User::class, 'survey_respondents', 'survey_id', 'user_id');
    }
    
    /**
     * Get the deadline as a combined datetime string.
     */
    public function getDeadlineAttribute()
    {
        if (!$this->deadline_date) {
            return null;
        }
        
        $time = $this->deadline_time ?? '23:59:59';
        return $this->deadline_date . ' ' . $time;
    }
}