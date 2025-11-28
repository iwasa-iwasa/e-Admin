<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;


class Reminder extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'reminders';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'reminder_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'deadline_date',
        'deadline_time',
        'category',
        'completed',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'reminder_id' => 'integer',
        'user_id' => 'integer',
        'deadline_date' => 'date:Y-m-d',
        'completed' => 'boolean',
        'completed_at' => 'datetime',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user that owns the reminder.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the trash items for the reminder.
     */
    public function trashItems()
    {
        return $this->hasMany(TrashItem::class, 'item_id')->where('item_type', 'reminder');
    }
}