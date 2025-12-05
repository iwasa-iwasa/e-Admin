<?php

namespace App\Http\Controllers;

use App\Models\SharedNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class NoteController extends Controller
{
    /**
     * Display the notes page.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $user = Auth::user();
        $notes = SharedNote::with(['author', 'tags', 'participants'])
            ->active()
            ->where(function($query) use ($user) {
                $query->where('author_id', $user->id)
                      ->orWhereHas('participants', function($q) use ($user) {
                          $q->where('users.id', $user->id);
                      })
                      ->orWhereDoesntHave('participants');
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        // 認証ユーザーがピン留めしたノートのIDリストを取得
        $pinnedNoteIds = $user->pinnedNotes()->pluck('shared_notes.note_id')->all();

        // 各ノートにis_pinned属性を付与
        $notes->each(function ($note) use ($pinnedNoteIds) {
            $note->is_pinned = in_array($note->note_id, $pinnedNoteIds);
        });

        // is_pinnedを優先してソート（ピン留めが先頭）、次に更新日でソート（DB取得時に適用済み）
        $sortedNotes = $notes->sortByDesc('is_pinned');

        // 現在のユーザーと同じ部署のメンバーを取得（作成者以外）
        $teamMembers = \App\Models\User::where('department', $user->department)
            ->where('id', '!=', $user->id)
            ->get();

        // すべてのタグを使用回数順で取得
        $allTags = \App\Models\NoteTag::withCount('sharedNotes')
            ->orderBy('shared_notes_count', 'desc')
            ->pluck('tag_name')
            ->values();

        return Inertia::render('Notes', [
            'notes' => $sortedNotes->values(), // ソート後にキーをリセット
            'totalUsers' => \App\Models\User::where('department', $user->department)->count(),
            'teamMembers' => $teamMembers,
            'allTags' => $allTags,
        ]);
    }

    /**
     * 新しい共有メモをデータベースに保存する。
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'in:yellow,blue,green,pink,purple'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'deadline' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'participants' => ['nullable', 'array'],
            'participants.*' => ['exists:users,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'pinned' => ['nullable', 'boolean'],
        ]);

        // deadlineをdeadline_dateとdeadline_timeに分割
        $deadlineDate = null;
        $deadlineTime = null;
        if (!empty($validated['deadline'])) {
            $datetime = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $validated['deadline']);
            $deadlineDate = $datetime->format('Y-m-d');
            $deadlineTime = $datetime->format('H:i:s');
        }

        $note = SharedNote::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'author_id' => Auth::id(),
            'color' => $validated['color'] ?? 'yellow',
            'priority' => $validated['priority'] ?? 'medium',
            'deadline_date' => $deadlineDate,
            'deadline_time' => $deadlineTime,
            'progress' => $validated['progress'] ?? 0,
        ]);

        // Add participants (if empty, everyone can see it)
        if (isset($validated['participants']) && !empty($validated['participants'])) {
            $note->participants()->attach($validated['participants']);
        }

        // Add tags
        if (isset($validated['tags']) && !empty($validated['tags'])) {
            $tagIds = [];
            foreach ($validated['tags'] as $tagName) {
                $tag = \App\Models\NoteTag::firstOrCreate(['tag_name' => $tagName]);
                $tagIds[] = $tag->tag_id;
            }
            $note->tags()->attach($tagIds);
        }

        // Pin note if requested
        if (isset($validated['pinned']) && $validated['pinned']) {
            $request->user()->pinnedNotes()->attach($note->note_id);
        }

        return redirect()->route('dashboard', ['selectNote' => $note->note_id])->with('success', '新しい共有メモを作成しました！');
    }

    /**
     * 指定されたノートをピン留めします。
     */
    public function pin(Request $request, SharedNote $note)
    {
        $request->user()->pinnedNotes()->attach($note->note_id);

        return back()->with('success', 'ノートをピン留めしました。');
    }

    /**
     * 指定されたノートのピン留めを解除します。
     */
    public function unpin(Request $request, SharedNote $note)
    {
        $request->user()->pinnedNotes()->detach($note->note_id);

        return back()->with('success', 'ノートのピン留めを解除しました。');
    }

    /**
     * 共有メモを更新します。
     */
    public function update(Request $request, SharedNote $note)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'in:yellow,blue,green,pink,purple'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'deadline' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'participants' => ['nullable', 'array'],
            'participants.*' => ['exists:users,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ]);

        // deadlineをdeadline_dateとdeadline_timeに分割
        $deadlineDate = null;
        $deadlineTime = null;
        if (!empty($validated['deadline'])) {
            $datetime = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $validated['deadline']);
            $deadlineDate = $datetime->format('Y-m-d');
            $deadlineTime = $datetime->format('H:i:s');
        }

        $note->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'color' => $validated['color'] ?? 'yellow',
            'priority' => $validated['priority'] ?? 'medium',
            'deadline_date' => $deadlineDate,
            'deadline_time' => $deadlineTime,
            'progress' => $validated['progress'] ?? 0,
        ]);

        // Update participants
        if (isset($validated['participants'])) {
            $note->participants()->sync($validated['participants']);
        }

        // Update tags
        if (isset($validated['tags'])) {
            $tagIds = [];
            foreach ($validated['tags'] as $tagName) {
                $tag = \App\Models\NoteTag::firstOrCreate(['tag_name' => $tagName]);
                $tagIds[] = $tag->tag_id;
            }
            $note->tags()->sync($tagIds);
        }

        // Update linked calendar event
        if ($note->linked_event_id) {
            $event = \App\Models\Event::find($note->linked_event_id);
            
            if ($event) {
                $colorCategoryMap = [
                    'blue' => '会議',
                    'green' => '業務',
                    'yellow' => '来客',
                    'purple' => '出張',
                    'pink' => '休暇',
                ];
                
                $event->update([
                    'title' => $validated['title'],
                    'description' => $validated['content'],
                    'category' => $colorCategoryMap[$validated['color']] ?? '会議',
                    'importance' => $validated['priority'] === 'high' ? '重要' : ($validated['priority'] === 'medium' ? '中' : '低'),
                    'end_date' => $deadlineDate,
                    'end_time' => $deadlineTime,
                ]);
                
                if (isset($validated['participants'])) {
                    $event->participants()->sync($validated['participants']);
                }
            }
        }

        return redirect()->route('dashboard', ['selectNote' => $note->note_id])->with('success', 'メモを更新しました。');
    }

    /**
     * 共有メモを削除します。
     */
    public function destroy(SharedNote $note)
    {
        \DB::transaction(function () use ($note) {
            // is_deletedフラグを設定
            $note->update([
                'is_deleted' => true,
                'deleted_at' => now(),
            ]);
            
            // ゴミ箱テーブルに記録
            \App\Models\TrashItem::create([
                'user_id' => Auth::id(),
                'item_type' => 'shared_note',
                'item_id' => $note->note_id,
                'original_title' => $note->title,
                'deleted_at' => now(),
                'permanent_delete_at' => now()->addDays(30),
            ]);
        });
        
        return back()->with('success', 'メモを削除しました。');
    }

    /**
     * 削除された共有メモを復元します。
     */
    public function restore($noteId)
    {
        \DB::transaction(function () use ($noteId) {
            // メモを復元
            $note = SharedNote::findOrFail($noteId);
            $note->update([
                'is_deleted' => false,
                'deleted_at' => null,
            ]);
            
            // ゴミ箱テーブルから削除
            \App\Models\TrashItem::where('item_type', 'shared_note')
                ->where('item_id', $noteId)
                ->where('user_id', Auth::id())
                ->delete();
        });
        
        return back()->with('success', 'メモを復元しました。');
    }
}