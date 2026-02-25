<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'department_id');
    }

    public function calendars()
    {
        return $this->hasMany(Calendar::class, 'owner_id')->where('owner_type', 'department');
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'owner_department_id');
    }

    public function sharedNotes()
    {
        return $this->hasMany(SharedNote::class, 'owner_department_id');
    }

    public function surveys()
    {
        return $this->hasMany(Survey::class, 'owner_department_id');
    }
}
