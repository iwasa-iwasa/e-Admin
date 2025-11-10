<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SurveyAnswer extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'survey_answers';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'answer_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'response_id',
        'question_id',
        'answer_text',
        'selected_option_id',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the response that this answer belongs to.
     */
    public function response()
    {
        return $this->belongsTo(SurveyResponse::class, 'response_id');
    }

    /**
     * Get the question that this answer belongs to.
     */
    public function question()
    {
        return $this->belongsTo(SurveyQuestion::class, 'question_id');
    }

    /**
     * Get the option that was selected for this answer.
     */
    public function selectedOption()
    {
        return $this->belongsTo(SurveyQuestionOption::class, 'selected_option_id');
    }
}