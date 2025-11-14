<?php

namespace App\Http\Controllers;

use App\Models\TrashItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TrashController extends Controller
{
    /**
     * Display the trash page.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        try {
            \Log::info('TrashController index called for user: ' . Auth::id());
            
            $trashItems = TrashItem::where('user_id', Auth::id())
                ->orderBy('deleted_at', 'desc')
                ->get();
                
            \Log::info('Found trash items: ' . $trashItems->count());
            
            $mappedItems = $trashItems->map(function ($item) {
                return [
                    'id' => (string)$item->trash_id,
                    'type' => $item->item_type,
                    'title' => $item->original_title,
                    'deletedAt' => $item->deleted_at->format('Y-m-d H:i'),
                    'item_id' => (string)$item->item_id,
                    'permanent_delete_at' => $item->permanent_delete_at->format('Y-m-d H:i'),
                ];
            });
            
            \Log::info('Mapped items: ' . $mappedItems->toJson());
            
        } catch (\Exception $e) {
            \Log::error('TrashController error: ' . $e->getMessage());
            $mappedItems = collect([]);
        }

        return Inertia::render('Trash', [
            'trashItems' => $mappedItems,
        ]);
    }

    /**
     * Restore an item from trash.
     */
    public function restore(Request $request, $id)
    {
        try {
            \Log::info('TrashController restore called with ID: ' . $id . ' for user: ' . Auth::id());
            
            $trashItem = TrashItem::where('trash_id', $id)
                ->where('user_id', Auth::id())
                ->first();
                
            if (!$trashItem) {
                \Log::error('TrashItem not found for ID: ' . $id);
                return back()->with('error', 'アイテムが見つかりません。');
            }
            
            \Log::info('Found trash item: ' . $trashItem->item_type . ' - ' . $trashItem->original_title);

            if ($trashItem->item_type === 'shared_note') {
                \DB::transaction(function () use ($trashItem) {
                    // メモを復元
                    $note = \App\Models\SharedNote::findOrFail($trashItem->item_id);
                    $note->update([
                        'is_deleted' => false,
                        'deleted_at' => null,
                    ]);
                    
                    // ゴミ箱テーブルから削除
                    $trashItem->delete();
                });
                
                \Log::info('Successfully restored shared note');
                return back()->with('success', 'メモを復元しました。');
            }

            return back()->with('error', 'サポートされていないアイテムタイプです。');
        } catch (\Exception $e) {
            \Log::error('TrashController restore error: ' . $e->getMessage());
            return back()->with('error', '復元に失敗しました。');
        }
    }

    /**
     * Permanently delete an item.
     */
    public function destroy($id)
    {
        $trashItem = TrashItem::where('trash_id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($trashItem->item_type === 'shared_note') {
            // メモを完全削除
            \App\Models\SharedNote::where('note_id', $trashItem->item_id)
                ->delete();
        }

        // ゴミ箱からも削除
        $trashItem->delete();

        return back()->with('success', 'アイテムを完全に削除しました。');
    }

    /**
     * Empty the entire trash.
     */
    public function emptyTrash()
    {
        $trashItems = TrashItem::where('user_id', Auth::id())->get();

        foreach ($trashItems as $item) {
            if ($item->item_type === 'shared_note') {
                \App\Models\SharedNote::where('note_id', $item->item_id)
                    ->delete();
            }
        }

        TrashItem::where('user_id', Auth::id())->delete();

        return back()->with('success', 'ゴミ箱を空にしました。');
    }
}