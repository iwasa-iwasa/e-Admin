<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use App\Models\Event;
use App\Models\SharedNote;
use App\Models\Survey;
use App\Models\Calendar;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * 部署統廃合サービス
 * 要件定義書 §5.3 に基づく
 */
class DepartmentMergeService
{
    /**
     * 複数の部署を1つに統合する
     *
     * @param array $sourceDepartmentIds 統合元の部署IDリスト
     * @param int $targetDepartmentId 統合先の部署ID
     * @param string $reason 統合理由
     * @return array 処理統計
     */
    public function mergeDepartments(
        array $sourceDepartmentIds,
        int $targetDepartmentId,
        string $reason = ''
    ): array {
        $stats = [
            'moved_users' => 0,
            'moved_events' => 0,
            'moved_notes' => 0,
            'moved_surveys' => 0,
        ];

        DB::transaction(function () use ($sourceDepartmentIds, $targetDepartmentId, $reason, &$stats) {
            $targetCalendar = $this->getTargetCalendar($targetDepartmentId);

            // 1. ユーザーを移動
            $stats['moved_users'] = User::whereIn('department_id', $sourceDepartmentIds)
                ->update(['department_id' => $targetDepartmentId]);

            // 2. 予定を移動
            $stats['moved_events'] = Event::whereIn('owner_department_id', $sourceDepartmentIds)
                ->update([
                    'owner_department_id' => $targetDepartmentId,
                    'calendar_id' => $targetCalendar->calendar_id,
                ]);

            // 3. 共有メモを移動
            $stats['moved_notes'] = SharedNote::whereIn('owner_department_id', $sourceDepartmentIds)
                ->update(['owner_department_id' => $targetDepartmentId]);

            // 4. アンケートを移動
            $stats['moved_surveys'] = Survey::whereIn('owner_department_id', $sourceDepartmentIds)
                ->update(['owner_department_id' => $targetDepartmentId]);

            // 5. 統合元の部署を無効化
            foreach ($sourceDepartmentIds as $sourceId) {
                Department::find($sourceId)?->update(['is_active' => false]);

                // 統合元のカレンダーを削除
                Calendar::where('owner_type', 'department')
                    ->where('owner_id', $sourceId)
                    ->delete();

                // 監査ログを記録
                AuditLog::create([
                    'action' => 'department_merged',
                    'user_id' => auth()->id() ?? 0,
                    'details' => json_encode([
                        'source_department_id' => $sourceId,
                        'target_department_id' => $targetDepartmentId,
                        'reason' => $reason,
                        'stats' => $stats,
                    ]),
                ]);
            }
        });

        return $stats;
    }

    /**
     * 統合先のカレンダーを取得
     */
    private function getTargetCalendar(int $departmentId): Calendar
    {
        return Calendar::where('owner_type', 'department')
            ->where('owner_id', $departmentId)
            ->firstOrFail();
    }
}
