<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SurveyResponse extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'survey_responses';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'response_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'survey_id',
        'respondent_id',
        'submitted_at',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    /**
     * Get the survey that this response belongs to.
     */
    public function survey()
    {
        return $this->belongsTo(Survey::class, 'survey_id', 'survey_id');
    }

    /**
     * Get the user who responded.
     */
    public function respondent()
    {
        return $this->belongsTo(User::class, 'respondent_id', 'id');
    }

    /**
     * Get the answers for the response.
     */
    public function answers()
    {
        return $this->hasMany(SurveyAnswer::class, 'response_id', 'response_id');
    }
}