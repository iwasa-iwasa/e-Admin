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
        'created_by',
        'deadline',
        'is_active',
        'is_deleted',
        'deleted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deadline' => 'datetime',
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
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
}