<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Models\Calendar;
use App\Services\DepartmentMergeService;
use App\Services\UserDepartmentTransferService;
use App\Services\UserDeactivationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * 部署管理コントローラー
 * 部署のCRUD操作と管理機能を提供する
 */
class DepartmentController extends Controller
{
    /**
     * 部署一覧を取得（API）
     */
    public function index()
    {
        $departments = Department::where('is_active', true)
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json($departments);
    }

    /**
     * 管理者向け部署管理画面を描画
     */
    public function manage()
    {
        $departments = Department::withCount('users')
            ->with('adminUser')
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Departments/Index', [
            'departments' => $departments,
            'users' => $users,
        ]);
    }

    /**
     * 全部署一覧（非アクティブ含む）を取得（API）
     */
    public function all()
    {
        $departments = Department::withCount('users')
            ->orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json($departments);
    }

    /**
     * 部署を作成
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:departments',
            'admin_user_id' => 'required|exists:users,id',
        ]);

        \DB::transaction(function () use ($validated) {
            // 部署を作成（Observer がカレンダーを自動生成）
            $department = Department::create(['name' => $validated['name']]);

            // 管理者ユーザーを部署に割り当て
            User::find($validated['admin_user_id'])->update([
                'department_id' => $department->id,
                'role_type' => 'department_admin',
            ]);
        });

        return redirect()->back()->with('success', '部署を作成しました');
    }

    /**
     * 部署を更新
     */
    public function update(Request $request, Department $department)
    {
        if (auth()->user()->role_type !== 'company_admin') {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:departments,name,' . $department->id,
            'admin_user_id' => 'nullable|exists:users,id',
        ]);

        \DB::transaction(function () use ($department, $validated) {
            $department->update(['name' => $validated['name']]);

            // 管理者の変更処理
            if (array_key_exists('admin_user_id', $validated) && !empty($validated['admin_user_id'])) {
                $currentAdmin = User::where('department_id', $department->id)
                                    ->where('role_type', 'department_admin')
                                    ->first();

                if (!$currentAdmin || $currentAdmin->id !== (int)$validated['admin_user_id']) {
                    // 既存の管理者がいれば一般ユーザーへ降格
                    if ($currentAdmin) {
                        $currentAdmin->update(['role_type' => 'member']);
                    }

                    // 新しい管理者を設定し、所属部署もその部署へ変更する
                    User::find($validated['admin_user_id'])->update([
                        'department_id' => $department->id,
                        'role_type' => 'department_admin',
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', '部署情報を更新しました');
    }

    /**
     * 部署を無効化（削除ではなく無効化）
     */
    public function destroy(Department $department)
    {
        // ユーザーが存在する場合は無効化のみ
        if ($department->users()->where('is_active', true)->exists()) {
            return redirect()->back()->withErrors([
                'department' => 'アクティブなユーザーが存在するため削除できません。先にユーザーを他の部署に異動してください。',
            ]);
        }

        $department->update(['is_active' => false]);

        return redirect()->back()->with('success', '部署を無効化しました');
    }

    /**
     * 部署のメンバー一覧を取得（API）
     */
    public function members(Department $department)
    {
        $members = User::where('department_id', $department->id)
            ->where('is_active', true)
            ->with('department')
            ->orderBy('role_type', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json($members);
    }

    /**
     * 部署統廃合
     */
    public function merge(Request $request, DepartmentMergeService $mergeService)
    {
        $validated = $request->validate([
            'source_department_ids' => 'required|array|min:1',
            'source_department_ids.*' => 'exists:departments,id',
            'target_department_id' => 'required|exists:departments,id',
            'reason' => 'nullable|string|max:500',
        ]);

        // 統合元と統合先が同じでないか検証
        if (in_array($validated['target_department_id'], $validated['source_department_ids'])) {
            return redirect()->back()->withErrors([
                'target_department_id' => '統合先は統合元と異なる部署を選択してください。',
            ]);
        }

        $stats = $mergeService->mergeDepartments(
            $validated['source_department_ids'],
            $validated['target_department_id'],
            $validated['reason'] ?? ''
        );

        return redirect()->back()->with('success', '部署を統合しました。移動ユーザー: ' . $stats['moved_users'] . '名');
    }

    /**
     * ユーザーの部署異動
     */
    public function transferUser(Request $request, User $user, UserDepartmentTransferService $transferService)
    {
        $validated = $request->validate([
            'new_department_id' => 'required|exists:departments,id',
        ]);

        $result = $transferService->transferDepartment($user, $validated['new_department_id']);

        if (isset($result['requires_confirmation'])) {
            return redirect()->back()->with('transfer_confirmation', $result);
        }

        return redirect()->back()->with('success', '部署異動が完了しました');
    }

    /**
     * ユーザーの部署異動確認後処理
     */
    public function confirmTransferUser(Request $request, User $user, UserDepartmentTransferService $transferService)
    {
        $validated = $request->validate([
            'new_department_id' => 'required|exists:departments,id',
            'option' => 'required|in:transfer,keep',
        ]);

        $result = $transferService->confirmTransfer(
            $user,
            $validated['new_department_id'],
            $validated['option']
        );

        return redirect()->back()->with('success', '部署異動が完了しました');
    }

    /**
     * ユーザー無効化（退職者処理）
     */
    public function deactivateUser(Request $request, User $user, UserDeactivationService $deactivationService)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $stats = $deactivationService->deactivateUser($user, $validated['reason']);

        return redirect()->back()->with('success', 'ユーザーを無効化しました。移譲: 予定' . $stats['transferred_events'] . '件、メモ' . $stats['transferred_notes'] . '件');
    }
}
