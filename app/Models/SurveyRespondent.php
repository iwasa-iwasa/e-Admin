<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyRespondent extends Model
{
    protected $fillable = [
        'survey_id',
        'user_id',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class, 'survey_id', 'survey_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
