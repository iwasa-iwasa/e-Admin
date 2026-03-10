<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Department;

class TrashItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'item_type',
        'is_shared',
        'item_id',
        'original_title',
        'creator_name',
        'owner_department_id',
        'visibility_type',
        'deleted_at',
        'permanent_delete_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'item_id' => 'integer',
        'owner_department_id' => 'integer',
        'is_shared' => 'boolean',
        'deleted_at' => 'datetime',
        'permanent_delete_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 繧ｴ繝溽ｮｱ繧｢繧､繝・Β縺ｮ謇譛蛾Κ鄂ｲ繧貞叙蠕・     */
    public function ownerDepartment()
    {
        return $this->belongsTo(Department::class, 'owner_department_id');
    }
}