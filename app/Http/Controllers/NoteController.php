<?php

namespace App\Http\Controllers;

use App\Models\SharedNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

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
        $notes = SharedNote::with(['author', 'tags'])
            ->whereNull('deleted_at')
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

        return Inertia::render('Notes', [
            'notes' => $sortedNotes->values(), // ソート後にキーをリセット
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
            'deadline' => ['nullable', 'date'],
        ]);

        SharedNote::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'author_id' => Auth::id(),
            'color' => $validated['color'] ?? 'yellow',
            'priority' => $validated['priority'] ?? 'medium',
            'deadline' => $validated['deadline'],
        ]);

        return redirect()->route('notes')->with('success', '新しい共有メモを作成しました！');
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
            'deadline' => ['nullable', 'date'],
        ]);

        $note->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'color' => $validated['color'] ?? 'yellow',
            'priority' => $validated['priority'] ?? 'medium',
            'deadline' => $validated['deadline'],
        ]);

        return back()->with('success', 'メモを更新しました。');
    }

    /**
     * 共有メモを削除します。
     */
    public function destroy(SharedNote $note)
    {
        \DB::transaction(function () use ($note) {
            // ソフトデリート
            $note->delete();
            
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
            $note = SharedNote::withTrashed()->findOrFail($noteId);
            $note->restore();
            
            // ゴミ箱テーブルから削除
            \App\Models\TrashItem::where('item_type', 'shared_note')
                ->where('item_id', $noteId)
                ->where('user_id', Auth::id())
                ->delete();
        });
        
        return back()->with('success', 'メモを復元しました。');
    }
}