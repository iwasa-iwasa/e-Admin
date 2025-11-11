<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
}

