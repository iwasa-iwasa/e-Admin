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
 * 部署異動サービス
 * 要件定義書 §5.1 に基づく
 */
class UserDepartmentTransferService
{
    /**
     * ユーザーの部署異動処理
     *
     * @param User $user 異動対象ユーザー
     * @param int $newDepartmentId 異動先部署ID
     * @return array 処理結果
     */
    public function transferDepartment(User $user, int $newDepartmentId): array
    {
        $oldDepartmentId = $user->department_id;
        $today = Carbon::today();

        DB::beginTransaction();

        try {
            // 1. 過去の予定: 元の部署に残す + 所有権移譲
            $this->transferPastEvents($user, $oldDepartmentId, $today);

            // 2. 繰り返し予定: 元の部署に残す
            $this->keepRecurringEvents($user, $oldDepartmentId);

            // 3. 未来の単発予定: 参加者をチェック
            $soloEvents = $this->checkSoloEvents($user, $oldDepartmentId, $today);

            if ($soloEvents->isNotEmpty()) {
                DB::rollBack();
                return [
                    'requires_confirmation' => true,
                    'solo_events' => $soloEvents,
                ];
            }

            // 4. 未来の予定（参加者が複数）: 新しい部署に移動
            $this->transferFutureEvents($user, $oldDepartmentId, $newDepartmentId, $today);

            // 5. ユーザーの部署を更新
            $user->update(['department_id' => $newDepartmentId]);

            // 6. 監査ログ記録
            AuditLog::create([
                'action' => 'user_transferred',
                'user_id' => $user->id,
                'details' => json_encode([
                    'from_department_id' => $oldDepartmentId,
                    'to_department_id' => $newDepartmentId,
                ]),
            ]);

            DB::commit();
            return ['success' => true];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 確認後の異動処理（単発予定の扱いを指定）
     *
     * @param User $user 異動対象ユーザー
     * @param int $newDepartmentId 異動先部署ID
     * @param string $option 'transfer'（新部署に移動）または 'keep'（元部署に残す）
     * @return array 処理結果
     */
    public function confirmTransfer(User $user, int $newDepartmentId, string $option): array
    {
        $oldDepartmentId = $user->department_id;
        $today = Carbon::today();

        DB::beginTransaction();

        try {
            // 過去・繰り返し予定の処理
            $this->transferPastEvents($user, $oldDepartmentId, $today);
            $this->keepRecurringEvents($user, $oldDepartmentId);

            // 単発予定の処理
            $soloEvents = Event::where('created_by', $user->id)
                ->where('owner_department_id', $oldDepartmentId)
                ->where('start_date', '>=', $today)
                ->whereDoesntHave('recurrence')
                ->whereDoesntHave('participants', function ($q) use ($user) {
                    $q->where('user_id', '!=', $user->id);
                })
                ->get();

            if ($option === 'transfer') {
                // 新部署に移動
                foreach ($soloEvents as $event) {
                    $event->update(['owner_department_id' => $newDepartmentId]);
                }
            } else {
                // 元部署に残す（所有権移譲）
                $departmentAdmin = $this->getDepartmentAdmin($oldDepartmentId);
                foreach ($soloEvents as $event) {
                    $event->update(['created_by' => $departmentAdmin->id]);
                }
            }

            // 複数人の未来の予定は新部署に移動
            $this->transferFutureEvents($user, $oldDepartmentId, $newDepartmentId, $today);

            // ユーザーの部署を更新
            $user->update(['department_id' => $newDepartmentId]);

            DB::commit();
            return ['success' => true];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 過去の予定の所有権を部署管理者に移譲
     */
    private function transferPastEvents(User $user, ?int $oldDepartmentId, Carbon $today): void
    {
        if (!$oldDepartmentId) {
            return;
        }

        $departmentAdmin = $this->getDepartmentAdmin($oldDepartmentId);
        if (!$departmentAdmin) {
            return;
        }

        Event::where('created_by', $user->id)
            ->where('owner_department_id', $oldDepartmentId)
            ->where('end_date', '<', $today)
            ->update(['created_by' => $departmentAdmin->id]);
    }

    /**
     * 繰り返し予定を元の部署に残す（所有権移譲）
     */
    private function keepRecurringEvents(User $user, ?int $oldDepartmentId): void
    {
        if (!$oldDepartmentId) {
            return;
        }

        $departmentAdmin = $this->getDepartmentAdmin($oldDepartmentId);
        if (!$departmentAdmin) {
            return;
        }

        Event::where('created_by', $user->id)
            ->where('owner_department_id', $oldDepartmentId)
            ->whereHas('recurrence')
            ->update(['created_by' => $departmentAdmin->id]);
    }

    /**
     * 本人のみが参加者の未来の単発予定を取得
     */
    private function checkSoloEvents(User $user, ?int $oldDepartmentId, Carbon $today)
    {
        if (!$oldDepartmentId) {
            return collect();
        }

        return Event::where('created_by', $user->id)
            ->where('owner_department_id', $oldDepartmentId)
            ->where('start_date', '>=', $today)
            ->whereDoesntHave('recurrence')
            ->with(['calendar', 'participants'])
            ->get()
            ->filter(function ($event) use ($user) {
                // 参加者が本人のみ、または参加者なし
                $otherParticipants = $event->participants->where('id', '!=', $user->id);
                return $otherParticipants->isEmpty();
            });
    }

    /**
     * 複数参加者がいる未来の予定を新部署に移動
     */
    private function transferFutureEvents(User $user, ?int $oldDepartmentId, int $newDepartmentId, Carbon $today): void
    {
        if (!$oldDepartmentId) {
            return;
        }

        Event::where('created_by', $user->id)
            ->where('owner_department_id', $oldDepartmentId)
            ->where('start_date', '>=', $today)
            ->whereDoesntHave('recurrence')
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('user_id', '!=', $user->id);
            })
            ->update(['owner_department_id' => $newDepartmentId]);
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
