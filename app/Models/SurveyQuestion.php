<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;


class SurveyQuestion extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'survey_questions';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'question_id';

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
        'survey_id',
        'question_text',
        'question_type',
        'is_required',
        'display_order',
        'scale_min',
        'scale_max',
        'scale_min_label',
        'scale_max_label',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'question_id' => 'integer',
        'survey_id' => 'integer',
        'is_required' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Get the survey that this question belongs to.
     */
    public function survey()
    {
        return $this->belongsTo(Survey::class, 'survey_id');
    }

    /**
     * Get the options for the question.
     */
    public function options()
    {
        return $this->hasMany(SurveyQuestionOption::class, 'question_id');
    }

    /**
     * Get the answers for the question.
     */
    public function answers()
    {
        // return $this->hasMany(Answer::class, 'question_id');
    }
}