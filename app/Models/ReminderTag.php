<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderTag extends Model
{
    protected $table = 'reminder_tags';
    protected $primaryKey = 'tag_id';
    
    protected $fillable = [
        'user_id',
        'tag_name',
    ];
    
    public function reminders()
    {
        return $this->belongsToMany(Reminder::class, 'reminder_tag_relations', 'tag_id', 'reminder_id', 'tag_id', 'reminder_id');
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
