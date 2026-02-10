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
     * Get a single reminder for API.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $reminder = Reminder::with(['tags'])
            ->where('reminder_id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
        return response()->json($reminder);
    }

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
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
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
        $reminder = Reminder::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'description' => $validated['description'],
            'deadline_date' => $deadlineDate,
            'deadline_time' => $deadlineTime,
            'completed' => $request->has('completed') ? (bool)$request->completed : false,
        ]);
        
        // タグを保存
        if (!empty($validated['tags'])) {
            $tagIds = [];
            foreach ($validated['tags'] as $tagName) {
                $tag = \App\Models\ReminderTag::firstOrCreate([
                    'user_id' => Auth::id(),
                    'tag_name' => $tagName,
                ]);
                $tagIds[] = $tag->tag_id;
            }
            $reminder->tags()->sync($tagIds);
        }

        return Redirect::back();
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
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
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
        ]);
        
        // タグを更新
        if (isset($validated['tags'])) {
            $tagIds = [];
            foreach ($validated['tags'] as $tagName) {
                $tag = \App\Models\ReminderTag::firstOrCreate([
                    'user_id' => Auth::id(),
                    'tag_name' => $tagName,
                ]);
                $tagIds[] = $tag->tag_id;
            }
            $reminder->tags()->sync($tagIds);
        } else {
            $reminder->tags()->sync([]);
        }

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

    /**
     * Bulk complete reminders.
     */
    public function bulkComplete(Request $request)
    {
        $ids = $request->input('ids', []);
        
        try {
            DB::transaction(function () use ($ids) {
                $reminders = Reminder::whereIn('reminder_id', $ids)
                    ->where('user_id', auth()->id())
                    ->get();
                
                foreach ($reminders as $reminder) {
                    TrashItem::create([
                        'user_id' => auth()->id(),
                        'item_type' => 'reminder',
                        'item_id' => $reminder->reminder_id,
                        'original_title' => $reminder->title,
                        'deleted_at' => now(),
                        'permanent_delete_at' => now()->addDays(30),
                    ]);
                    
                    $reminder->update([
                        'completed' => 1,
                        'completed_at' => now(),
                    ]);
                }
            });
            
            return redirect()->back();
        } catch (\Exception $e) {
            \Log::error('Bulk complete error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => '一括完了に失敗しました']);
        }
    }

    /**
     * Bulk restore reminders.
     */
    public function bulkRestore(Request $request)
    {
        $ids = $request->input('ids', []);
        
        try {
            DB::transaction(function () use ($ids) {
                $reminders = Reminder::whereIn('reminder_id', $ids)
                    ->where('user_id', auth()->id())
                    ->get();
                
                foreach ($reminders as $reminder) {
                    $reminder->update([
                        'completed' => 0,
                        'completed_at' => null,
                    ]);
                    
                    TrashItem::where('item_type', 'reminder')
                        ->where('item_id', $reminder->reminder_id)
                        ->where('user_id', auth()->id())
                        ->delete();
                }
            });
            
            return redirect()->back();
        } catch (\Exception $e) {
            \Log::error('Bulk restore error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => '一括復元に失敗しました']);
        }
    }

    /**
     * Bulk delete reminders permanently.
     */
    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        
        try {
            DB::transaction(function () use ($ids) {
                $reminders = Reminder::whereIn('reminder_id', $ids)
                    ->where('user_id', auth()->id())
                    ->get();
                
                foreach ($reminders as $reminder) {
                    TrashItem::where('item_type', 'reminder')
                        ->where('item_id', $reminder->reminder_id)
                        ->where('user_id', auth()->id())
                        ->delete();
                    
                    $reminder->delete();
                }
            });
            
            return redirect()->back();
        } catch (\Exception $e) {
            \Log::error('Bulk delete error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => '一括削除に失敗しました']);
        }
    }

    /**
     * Copy a reminder.
     */
    public function copy($id)
    {
        $original = Reminder::with('tags')
            ->where('reminder_id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
        
        try {
            $copy = Reminder::create([
                'user_id' => Auth::id(),
                'title' => $original->title . ' (コピー)',
                'description' => $original->description,
                'deadline_date' => $original->deadline_date,
                'deadline_time' => $original->deadline_time,
                'completed' => false,
            ]);
            
            if ($original->tags->isNotEmpty()) {
                $copy->tags()->sync($original->tags->pluck('tag_id'));
            }
            
            return Redirect::back();
        } catch (\Exception $e) {
            \Log::error('Copy reminder error: ' . $e->getMessage());
            return Redirect::back()->withErrors(['error' => 'コピーに失敗しました']);
        }
    }
}

