<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyEventRequest extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'event_data',
        'requested_by',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_comment',
    ];

    protected $casts = [
        'id' => 'integer',
        'event_data' => 'array',
        'requested_by' => 'integer',
        'reviewed_by' => 'integer',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
