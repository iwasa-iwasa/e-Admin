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
     * @param  int  $reminder
     * @return \Illuminate\Http\RedirectResponse
     */
    public function completeReminder($reminder, Request $request)
    {
        \Log::info('Complete reminder called', [
            'reminder_id' => $reminder,
            'auth_id' => auth()->id()
        ]);
        
        $reminderModel = Reminder::where('reminder_id', $reminder)
            ->where('user_id', auth()->id())
            ->first();
            
        if (!$reminderModel) {
            \Log::error('Reminder not found or permission denied', ['reminder_id' => $reminder, 'auth_id' => auth()->id()]);
            return redirect()->back()->withErrors(['error' => 'リマインダーが見つからないか権限がありません']);
        }
        
        \Log::info('Found reminder', [
            'reminder_id' => $reminderModel->reminder_id,
            'current_completed' => $reminderModel->completed,
            'user_id' => $reminderModel->user_id
        ]);
        
        try {
            DB::transaction(function () use ($reminderModel) {
                \Log::info('Starting complete transaction', ['reminder_id' => $reminderModel->reminder_id]);
                
                $updateResult = $reminderModel->update([
                    'completed' => 1,
                    'completed_at' => now(),
                ]);
                \Log::info('Reminder completed', ['result' => $updateResult, 'new_completed' => $reminderModel->fresh()->completed]);
                
                $trashData = [
                    'user_id' => auth()->id(),
                    'item_type' => 'reminder',
                    'item_id' => $reminderModel->reminder_id,
                    'original_title' => $reminderModel->title,
                    'deleted_at' => now(),
                    'permanent_delete_at' => now()->addDays(30),
                ];
                
                $trashItem = TrashItem::create($trashData);
                \Log::info('TrashItem created', ['trash_id' => $trashItem->trash_id]);
            });
            
            \Log::info('Complete transaction successful');
            return redirect()->back();
        } catch (\Exception $e) {
            \Log::error('Complete reminder error: ' . $e->getMessage());
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
        $reminderId = $request->input('reminder_id');
        \Log::info('Restore reminder called', [
            'reminder_id' => $reminderId,
            'auth_id' => auth()->id()
        ]);
        
        $reminder = Reminder::where('reminder_id', $reminderId)
            ->where('user_id', auth()->id())
            ->first();
        
        if (!$reminder) {
            \Log::error('Reminder not found', ['reminder_id' => $reminderId, 'auth_id' => auth()->id()]);
            return redirect()->back()->withErrors(['error' => 'リマインダーが見つかりません']);
        }
        
        \Log::info('Found reminder to restore', [
            'reminder_id' => $reminder->reminder_id,
            'current_completed' => $reminder->completed
        ]);
        
        try {
            DB::transaction(function () use ($reminder, $reminderId) {
                \Log::info('Starting restore transaction');
                
                $updateResult = $reminder->update([
                    'completed' => 0,
                    'completed_at' => null,
                ]);
                \Log::info('Reminder restored', ['result' => $updateResult, 'new_completed' => $reminder->fresh()->completed]);
                
                $deletedTrashItems = TrashItem::where('item_type', 'reminder')
                    ->where('item_id', $reminderId)
                    ->where('user_id', auth()->id())
                    ->delete();
                \Log::info('TrashItems deleted', ['count' => $deletedTrashItems]);
            });
            
            \Log::info('Restore transaction successful');
            return redirect()->back();
        } catch (\Exception $e) {
            \Log::error('Restore reminder error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'タスクの復元に失敗しました']);
        }
    }
}

