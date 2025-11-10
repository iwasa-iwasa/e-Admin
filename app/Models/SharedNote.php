<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SharedNote extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'shared_notes';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'note_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'content',
        'author_id',
        'color',
        'priority',
        'deadline',
        'pinned',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deadline' => 'date',
        'pinned' => 'boolean',
    ];

    /**
     * Get the author of the note.
     */
        public function author()
        {
            return $this->belongsTo(User::class, 'author_id');
        }
    
        /**
         * The tags that belong to the note.
         */
        public function tags()
        {
            return $this->belongsToMany(NoteTag::class, 'note_tag_relations', 'note_id', 'tag_id');
        }
    }
    