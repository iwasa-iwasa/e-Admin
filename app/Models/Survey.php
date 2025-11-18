<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;


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
        'survey_id' => 'integer',
        'created_by' => 'integer',
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
}