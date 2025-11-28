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
        // 空文字列をnullに変換
        $data = $request->all();
        if (isset($data['deadline']) && $data['deadline'] === '') {
            $data['deadline'] = null;
        }
        
        // バリデーション
        $validated = validator($data, [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'deadline' => 'nullable|date_format:Y-m-d\TH:i',
            'category' => 'required|string|max:50',
            'completed' => 'nullable|boolean',
        ])->validate();

        // deadlineをdeadline_dateとdeadline_timeに分割
        $deadlineDate = null;
        $deadlineTime = null;
        if (!empty($validated['deadline'])) {
            $datetime = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $validated['deadline']);
            $deadlineDate = $datetime->format('Y-m-d');
            $deadlineTime = $datetime->format('H:i:s');
        }

        // リマインダーを作成
        Reminder::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'description' => $validated['description'],
            'deadline_date' => $deadlineDate,
            'deadline_time' => $deadlineTime,
            'category' => $validated['category'],
            'completed' => $request->has('completed') ? (bool)$request->completed : false,
        ]);

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

        // 空文字列をnullに変換
        $data = $request->all();
        if (isset($data['deadline']) && $data['deadline'] === '') {
            $data['deadline'] = null;
        }
        
        // バリデーション
        $validated = validator($data, [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'deadline' => 'nullable|date_format:Y-m-d\TH:i',
            'category' => 'required|string|max:50',
        ])->validate();

        // deadlineをdeadline_dateとdeadline_timeに分割
        $deadlineDate = null;
        $deadlineTime = null;
        if (!empty($validated['deadline'])) {
            $datetime = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $validated['deadline']);
            $deadlineDate = $datetime->format('Y-m-d');
            $deadlineTime = $datetime->format('H:i:s');
        }

        // リマインダーを更新（completedは更新しない）
        $reminder->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'deadline_date' => $deadlineDate,
            'deadline_time' => $deadlineTime,
            'category' => $validated['category'],
        ]);

        return Redirect::back();
    }

    /**
     * Complete the specified reminder (soft delete).
     *
     * @param  int  $reminder
     * @return \Illuminate\Http\RedirectResponse
     */
    public function completeReminder($reminder, Request $request)
    {
        $reminderModel = Reminder::where('reminder_id', $reminder)
            ->where('user_id', auth()->id())
            ->first();
            
        if (!$reminderModel) {
            return redirect()->back()->withErrors(['error' => 'リマインダーが見つからないか権限がありません']);
        }
        
        try {
            DB::transaction(function () use ($reminderModel) {
                // TrashItemに追加
                TrashItem::create([
                    'user_id' => auth()->id(),
                    'item_type' => 'reminder',
                    'item_id' => $reminderModel->reminder_id,
                    'original_title' => $reminderModel->title,
                    'deleted_at' => now(),
                    'permanent_delete_at' => now()->addDays(30),
                ]);
                
                // リマインダーを完了状態にして削除扱い
                $reminderModel->update([
                    'completed' => 1,
                    'completed_at' => now(),
                ]);
            });
            
            return Redirect::back();
        } catch (\Exception $e) {
            \Log::error('Complete reminder error: ' . $e->getMessage());
            return Redirect::back()->withErrors(['error' => 'リマインダーの完了に失敗しました']);
        }
    }

    /**
     * Restore the specified reminder.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function restoreReminder(Request $request)
    {
        $reminderId = $request->input('reminder_id');
        
        $reminder = Reminder::where('reminder_id', $reminderId)
            ->where('user_id', auth()->id())
            ->first();
        
        if (!$reminder) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'リマインダーが見つかりません'], 404);
            }
            return redirect()->back()->withErrors(['error' => 'リマインダーが見つかりません']);
        }
        
        try {
            DB::transaction(function () use ($reminder, $reminderId) {
                // 完了状態を解除
                $reminder->update([
                    'completed' => 0,
                    'completed_at' => null,
                ]);
                
                // TrashItemから削除
                TrashItem::where('item_type', 'reminder')
                    ->where('item_id', $reminderId)
                    ->where('user_id', auth()->id())
                    ->delete();
            });
            
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'リマインダーが元に戻されました']);
            }
            return redirect()->back();
        } catch (\Exception $e) {
            \Log::error('Restore reminder error: ' . $e->getMessage());
            if ($request->expectsJson()) {
                return response()->json(['error' => 'タスクの復元に失敗しました'], 500);
            }
            return redirect()->back()->withErrors(['error' => 'タスクの復元に失敗しました']);
        }
    }

    /**
     * Permanently delete the specified reminder.
     *
     * @param  int  $reminder
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($reminder)
    {
        $reminderModel = Reminder::where('reminder_id', $reminder)
            ->where('user_id', auth()->id())
            ->first();
            
        if (!$reminderModel) {
            return redirect()->back()->withErrors(['error' => 'リマインダーが見つからないか権限がありません']);
        }
        
        try {
            DB::transaction(function () use ($reminderModel, $reminder) {
                // TrashItemから削除
                TrashItem::where('item_type', 'reminder')
                    ->where('item_id', $reminder)
                    ->where('user_id', auth()->id())
                    ->delete();
                
                // リマインダー本体を完全削除
                $reminderModel->delete();
            });
            
            return redirect()->back();
        } catch (\Exception $e) {
            \Log::error('Destroy reminder error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'リマインダーの削除に失敗しました']);
        }
    }
}

