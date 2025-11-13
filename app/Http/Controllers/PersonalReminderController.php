<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\TrashItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class PersonalReminderController extends Controller
{
    /**
     * Store a newly created reminder in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // バリデーション
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'deadline' => 'required|date',
            'category' => 'required|string|max:50',
            'completed' => 'nullable|boolean',
        ]);

        // 現在のユーザーIDを追加
        $validated['user_id'] = Auth::id();
        // completedが送信されていない場合はfalseに設定
        $validated['completed'] = $request->has('completed') ? (bool)$request->completed : false;

        // リマインダーを作成
        Reminder::create($validated);

        // ダッシュボードにリダイレクト（または元のページに戻る）
        return Redirect::back()->with('success', 'リマインダーを作成しました');
    }

    /**
     * Update the specified reminder in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $reminder = Reminder::where('reminder_id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // バリデーション
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'deadline' => 'required|date',
            'category' => 'required|string|max:50',
            'completed' => 'nullable|boolean',
        ]);

        // completedが送信されていない場合は現在の値を維持
        if (!$request->has('completed')) {
            $validated['completed'] = $reminder->completed;
        } else {
            $validated['completed'] = (bool)$request->completed;
        }

        // 完了ステータスが変更された場合、completed_atを更新
        $wasCompleted = $reminder->completed;
        $isNowCompleted = $validated['completed'];

        // リマインダーを更新
        $reminder->update($validated);

        // 完了状態が変更された場合、completed_atを更新
        if ($isNowCompleted && !$wasCompleted) {
            // 未完了から完了に変更
            $reminder->completed_at = now();
            $reminder->save();
        } elseif (!$isNowCompleted && $wasCompleted) {
            // 完了から未完了に変更
            $reminder->completed_at = null;
            $reminder->save();
        }

        return Redirect::back()->with('success', 'リマインダーを更新しました');
    }

    /**
     * Complete the specified reminder.
     *
     * @param  \App\Models\Reminder  $reminder
     * @return \Illuminate\Http\RedirectResponse
     */
    public function completeReminder(Reminder $reminder, Request $request)
    {
        // デバッグ用ログ
        \Log::info('Received ID and Data:', [
            'id' => $reminder->reminder_id,
            'request_all' => $request->all(),
            'completed_status_from_request' => $request->boolean('checked'),
            'auth_id' => auth()->id()
        ]);
        
        if ($reminder->user_id !== auth()->id()) {
            return redirect()->back()->withErrors(['error' => '権限がありません']);
        }
        
        try {
            DB::transaction(function () use ($reminder) {
                \Log::info('Starting transaction', ['reminder_id' => $reminder->reminder_id]);
                
                // Reminder更新のテスト
                $updateResult = $reminder->update([
                    'completed' => 1,
                    'completed_at' => now(),
                ]);
                \Log::info('Reminder update result', ['result' => $updateResult, 'completed' => $reminder->completed]);
                
                // TrashItem作成のテスト
                $trashData = [
                    'user_id' => auth()->id(),
                    'item_type' => 'reminder',
                    'item_id' => $reminder->reminder_id,
                    'original_title' => $reminder->title,
                    'deleted_at' => now(),
                    'permanent_delete_at' => now()->addDays(30),
                ];
                \Log::info('Creating TrashItem with data', $trashData);
                
                $trashItem = TrashItem::create($trashData);
                \Log::info('TrashItem created', ['trash_id' => $trashItem->trash_id]);
                
                \Log::info('Transaction completed successfully');
            });
            
            return redirect()->back()->with([
                'success' => 'タスクを完了しました',
                'reminder_id' => $reminder->reminder_id,
            ]);
        } catch (\Exception $e) {
            \Log::error('タスク完了エラー: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'タスクの完了に失敗しました']);
        }
    }

    /**
     * Restore the specified reminder.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restoreReminder(Request $request)
    {
        // デバッグ用ログ
        \Log::info('Received ID and Data:', [
            'request_all' => $request->all(),
            'reminder_id_from_request' => $request->input('reminder_id'),
            'auth_id' => auth()->id()
        ]);
        
        $reminderId = $request->input('reminder_id');
        
        $reminder = Reminder::where('reminder_id', $reminderId)
            ->where('user_id', auth()->id())
            ->first();
        
        if (!$reminder) {
            return redirect()->back()->withErrors(['error' => 'リマインダーが見つかりません']);
        }
        
        try {
            DB::transaction(function () use ($reminder, $reminderId) {
                $reminder->update([
                    'completed' => 0,
                    'completed_at' => null,
                ]);
                
                TrashItem::where('item_type', 'reminder')
                    ->where('item_id', $reminderId)
                    ->where('user_id', auth()->id())
                    ->delete();
            });
            
            return redirect()->back()->with('success', 'タスクを元に戻しました');
        } catch (\Exception $e) {
            \Log::error('タスク復元エラー: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'タスクの復元に失敗しました']);
        }
    }
}

