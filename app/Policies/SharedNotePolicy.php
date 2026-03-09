<?php

namespace App\Policies;

use App\Models\User;
use App\Models\SharedNote;

/**
 * 共有メモの権限ポリシー
 * 要件定義書 §4.1 に基づく
 */
class SharedNotePolicy
{
    /**
     * メモの閲覧権限
     */
    public function view(User $user, SharedNote $note): bool
    {
        // 1. 全社管理者は全て閲覧可能
        if ($user->role_type === 'company_admin') {
            return true;
        }

        // 2. 全社公開メモ
        if ($note->visibility_type === 'public') {
            return true;
        }

        // 3. 部署限定メモ（同じ部署のみ）
        if ($note->visibility_type === 'department'
            && $note->owner_department_id === $user->department_id) {
            return true;
        }

        // 4. カスタム（参加者のみ）
        if ($note->visibility_type === 'custom'
            && $note->participants->contains('id', $user->id)) {
            return true;
        }

        // 5. 作成者本人
        if ($note->author_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * メモの編集権限
     */
    public function update(User $user, SharedNote $note): bool
    {
        // 1. 作成者本人
        if ($note->author_id === $user->id) {
            return true;
        }

        // 2. 参加者として登録されている
        if ($note->participants->contains('id', $user->id)) {
            return true;
        }

        // 3. 部署管理者（自部署のメモのみ）
        if ($user->role_type === 'department_admin'
            && $note->owner_department_id === $user->department_id) {
            return true;
        }

        // 4. 全社管理者は全社公開メモのみ編集可能
        if ($user->role_type === 'company_admin'
            && $note->visibility_type === 'public') {
            return true;
        }

        return false;
    }

    /**
     * メモの削除権限
     */
    public function delete(User $user, SharedNote $note): bool
    {
        return $this->update($user, $note);
    }
}
