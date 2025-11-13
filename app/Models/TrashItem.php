<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrashItem extends Model
{
    protected $table = 'trash_items';
    protected $primaryKey = 'trash_id';
    public $timestamps = false;
    
    protected $fillable = [
        'user_id',
        'item_type',
        'item_id',
        'original_title',
        'deleted_at',
        'permanent_delete_at',
    ];
    
    protected $casts = [
        'deleted_at' => 'datetime',
        'permanent_delete_at' => 'datetime',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}