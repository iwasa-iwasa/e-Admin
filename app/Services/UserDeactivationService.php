<?php

namespace App\Services;

use App\Models\User;
use App\Models\Event;
use App\Models\SharedNote;
use App\Models\Survey;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ユーザー無効化（退職者処理）サービス
 * 要件定義書 §5.2 に基づく
 */
class UserDeactivationService
{
    /**
     * ユーザーを無効化し、データの所有権を移譲する
     *
     * @param User $user 無効化対象ユーザー
     * @param string $reason 無効化理由
     * @return array 処理統計
     */
    public function deactivateUser(User $user, string $reason): array
    {
        $stats = [
            'transferred_events' => 0,
            'transferred_notes' => 0,
            'transferred_surveys' => 0,
            'removed_from_events' => 0,
        ];

        DB::transaction(function () use ($user, $reason, &$stats) {
            $today = Carbon::today();
            $departmentAdmin = $this->getDepartmentAdmin($user->department_id);

            if ($departmentAdmin) {
                // 1. 未来の予定の所有権を移譲
                $stats['transferred_events'] = Event::where('created_by', $user->id)
                    ->where('start_date', '>=', $today)
                    ->update(['created_by' => $departmentAdmin->id]);

                // 2. 共有メモの所有権を移譲
                $stats['transferred_notes'] = SharedNote::where('author_id', $user->id)
                    ->where('is_deleted', false)
                    ->update(['author_id' => $departmentAdmin->id]);

                // 3. アクティブなアンケートの所有権を移譲
                $stats['transferred_surveys'] = Survey::where('created_by', $user->id)
                    ->where('is_active', true)
                    ->where('is_deleted', false)
                    ->update(['created_by' => $departmentAdmin->id]);
            }

            // 4. 未来の予定から参加者として除外
            $stats['removed_from_events'] = DB::table('event_participants')
                ->where('user_id', $user->id)
                ->whereIn('event_id', function ($query) use ($today) {
                    $query->select('event_id')
                        ->from('events')
                        ->where('start_date', '>=', $today)
                        ->whereNull('deleted_at');
                })
                ->delete();

            // 5. ユーザーを無効化
            $user->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'reason' => $reason,
            ]);

            // 6. 監査ログを記録
            AuditLog::create([
                'action' => 'user_deactivated',
                'user_id' => $user->id,
                'details' => json_encode(['reason' => $reason, 'stats' => $stats]),
            ]);
        });

        return $stats;
    }

    /**
     * 部署管理者を取得
     */
    private function getDepartmentAdmin(int $departmentId): ?User
    {
        return User::where('department_id', $departmentId)
            ->where('role_type', 'department_admin')
            ->where('is_active', true)
            ->first();
    }
}
