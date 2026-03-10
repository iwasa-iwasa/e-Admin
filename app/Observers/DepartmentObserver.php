<?php

namespace App\Observers;

use App\Models\Department;
use App\Models\Calendar;
use App\Models\Event;

/**
 * 部署モデルのオブザーバー
 * 部署の作成・更新・削除時に関連するカレンダーを自動管理する
 */
class DepartmentObserver
{
    /**
     * 部署作成時に自動的にカレンダーを作成
     */
    public function created(Department $department): void
    {
        Calendar::create([
            'calendar_name' => $department->name . 'カレンダー',
            'calendar_type' => 'shared',
            'owner_type' => 'department',
            'owner_id' => $department->id,
        ]);
    }

    /**
     * 部署名変更時にカレンダー名も更新
     */
    public function updated(Department $department): void
    {
        if ($department->isDirty('name')) {
            Calendar::where('owner_type', 'department')
                ->where('owner_id', $department->id)
                ->update(['calendar_name' => $department->name . 'カレンダー']);
        }
    }

    /**
     * 部署削除時の整合性チェック
     */
    public function deleting(Department $department): bool
    {
        // ユーザーまたは予定が存在する場合は削除不可
        $hasUsers = $department->users()->exists();
        $hasEvents = Event::where('owner_department_id', $department->id)->exists();

        if ($hasUsers || $hasEvents) {
            throw new \Exception('部署にユーザーまたは予定が存在するため削除できません');
        }

        // 関連カレンダーを削除
        Calendar::where('owner_type', 'department')
            ->where('owner_id', $department->id)
            ->delete();

        return true;
    }
}
