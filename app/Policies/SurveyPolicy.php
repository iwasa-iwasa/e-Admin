<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Survey;

/**
 * アンケートの権限ポリシー
 * 要件定義書 §4.1 に基づく
 */
class SurveyPolicy
{
    /**
     * アンケートの閲覧権限
     */
    public function view(User $user, Survey $survey): bool
    {
        // 1. 全社管理者は全て閲覧可能
        if ($user->role_type === 'company_admin') {
            return true;
        }

        // 2. 作成者本人
        if ($survey->created_by === $user->id) {
            return true;
        }

        // 3. 部署管理者（自部署のアンケート）
        if ($user->role_type === 'department_admin'
            && $survey->owner_department_id === $user->department_id) {
            return true;
        }

        // 4. 回答対象者として指定されている
        if ($survey->designatedUsers->contains('id', $user->id)) {
            return true;
        }

        // 5. 全社公開アンケート
        if ($survey->visibility_type === 'public') {
            return true;
        }

        return false;
    }

    /**
     * アンケートの編集権限
     */
    public function update(User $user, Survey $survey): bool
    {
        // 1. 全社管理者
        if ($user->role_type === 'company_admin') {
            return true;
        }

        // 2. 作成者本人
        if ($survey->created_by === $user->id) {
            return true;
        }

        // 3. 部署管理者（自部署のアンケート）
        if ($user->role_type === 'department_admin'
            && $survey->owner_department_id === $user->department_id) {
            return true;
        }

        return false;
    }

    /**
     * アンケートの削除権限
     */
    public function delete(User $user, Survey $survey): bool
    {
        return $this->update($user, $survey);
    }

    /**
     * 回答結果の閲覧権限
     */
    public function viewResults(User $user, Survey $survey): bool
    {
        // 編集権限と同じ
        return $this->update($user, $survey);
    }
}
