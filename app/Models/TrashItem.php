<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrashItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'item_type',
        'item_id',
        'original_title',
        'deleted_at',
        'permanent_delete_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'item_id' => 'integer',
        'deleted_at' => 'datetime',
        'permanent_delete_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}