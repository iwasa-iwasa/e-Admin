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
}