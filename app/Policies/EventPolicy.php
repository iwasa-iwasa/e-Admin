<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Event;
use App\Models\Calendar;

/**
 * イベント（カレンダー予定）の権限ポリシー
 * 要件定義書 §4.1 / §4.2 に基づく
 */
class EventPolicy
{
    /**
     * 予定の閲覧権限
     */
    public function view(User $user, Event $event): bool
    {
        // 1. 全社管理者は全て閲覧可能
        if ($user->role_type === 'company_admin') {
            return true;
        }

        // 2. 全社公開
        if ($event->visibility_type === 'public') {
            return true;
        }

        // 3. 部署限定（同じ部署のみ）
        if ($event->visibility_type === 'department'
            && $event->owner_department_id === $user->department_id) {
            return true;
        }

        // 4. カスタム（参加者のみ）
        if ($event->visibility_type === 'custom'
            && $event->participants->contains('id', $user->id)) {
            return true;
        }

        // 5. 作成者本人
        if ($event->created_by === $user->id) {
            return true;
        }

        // 6. 非公開（作成者のみ）
        if ($event->visibility_type === 'private'
            && $event->created_by === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * カレンダーへの予定作成権限
     */
    public function create(User $user, Calendar $calendar): bool
    {
        // 全社カレンダー: 全社管理者のみ
        if ($calendar->owner_type === 'company') {
            return $user->role_type === 'company_admin';
        }

        // 自部署カレンダー: 所属メンバーなら作成可能
        if ($calendar->owner_id === $user->department_id) {
            return true;
        }

        // 他部署カレンダー: 作成不可
        return false;
    }

    /**
     * 予定の編集権限
     */
    public function update(User $user, Event $event): bool
    {
        $calendar = $event->calendar;

        // 全社カレンダーの場合: 全社管理者のみ
        if ($calendar && $calendar->owner_type === 'company') {
            return $user->role_type === 'company_admin';
        }

        // 1. 作成者本人
        if ($event->created_by === $user->id) {
            return true;
        }

        // 2. 参加者として登録されている（他部署でも編集可能）
        if ($event->participants->contains('id', $user->id)) {
            return true;
        }

        // 3. 部署管理者（同じ部署のみ）
        if ($user->role_type === 'department_admin'
            && $event->owner_department_id === $user->department_id) {
            return true;
        }

        return false;
    }

    /**
     * 予定の削除権限
     */
    public function delete(User $user, Event $event): bool
    {
        // 編集権限と同じロジック
        return $this->update($user, $event);
    }

    /**
     * 予定の複製権限
     */
    public function duplicate(User $user, Event $event): bool
    {
        // 一般メンバー: 自部署の予定のみ複製可能
        if ($user->role_type === 'member') {
            return $event->owner_department_id === $user->department_id;
        }

        // 部署管理者・全社管理者: 全ての予定を複製可能
        return in_array($user->role_type, ['department_admin', 'company_admin']);
    }
}
