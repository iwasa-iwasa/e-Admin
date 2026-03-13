<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;

class AdminUserController extends Controller
{
    public function index()
    {
        $currentUser = auth()->user();
        
        // 全社管理者の場合は全ユーザーを表示、部署管理者の場合は自部署のみ
        $query = User::with('department');
        
        if ($currentUser->role_type === 'department_admin') {
            // 部署管理者は自分の部署のユーザーのみ表示
            $query->where('department_id', $currentUser->department_id);
        }
        
        $users = $query->get();
        
        // 管理者を上に、メンバーを下に並べ替え
        $sortedUsers = $users->sortBy([
            ['role_type', 'asc'], // company_admin, department_admin, memberの順
            ['department_id', 'asc'], // 部署別
            ['name', 'asc'] // 名前順
        ])->values();
        
        return Inertia::render('Admin/Users/Index', [
            'users' => $sortedUsers->map(fn($user) => \App\Data\UserData::fromModel($user)),
            'departments' => \App\Models\Department::where('is_active', true)->orderBy('name')->get(),
            'canManageAllUsers' => $currentUser->role_type === 'company_admin',
            'currentUserDepartmentId' => $currentUser->department_id,
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        $currentUser = auth()->user();
        
        // 権限チェック：全社管理者または同じ部署の部署管理者のみ
        if ($currentUser->role_type === 'department_admin' && $currentUser->department_id !== $user->department_id) {
            return Redirect::back()->withErrors(['message' => '他の部署のユーザーは管理できません。']);
        }
        
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        // Prevent deleting the last admin
        if ($user->role === 'admin' && User::where('role', 'admin')->where('is_active', true)->count() <= 1) {
            return Redirect::back()->withErrors(['message' => '最後の管理者は無効化できません。']);
        }

        $user->update([
            'is_active' => false,
            'deactivated_at' => now(),
            'reason' => $request->reason,
        ]);

        \App\Models\UserLog::create([
            'user_id' => $user->id,
            'changed_by' => auth()->id(),
            'type' => 'status',
            'old_value' => 'active',
            'new_value' => 'inactive',
            'reason' => $request->reason,
        ]);

        return Redirect::back();
    }

    public function restore(User $user)
    {
        $currentUser = auth()->user();
        
        // 権限チェック：全社管理者または同じ部署の部署管理者のみ
        if ($currentUser->role_type === 'department_admin' && $currentUser->department_id !== $user->department_id) {
            return Redirect::back()->withErrors(['message' => '他の部署のユーザーは管理できません。']);
        }
        
        $user->update([
            'is_active' => true,
            'deactivated_at' => null,
            'reason' => null,
        ]);

        \App\Models\UserLog::create([
            'user_id' => $user->id,
            'changed_by' => auth()->id(),
            'type' => 'status',
            'old_value' => 'inactive',
            'new_value' => 'active',
            'reason' => 'Admin restored user',
        ]);

        return Redirect::back();
    }

    public function updateRole(Request $request, User $user)
    {
        $currentUser = auth()->user();
        
        // 権限チェック：全社管理者のみが権限変更可能
        if ($currentUser->role_type !== 'company_admin') {
            return Redirect::back()->withErrors(['message' => '権限の変更は全社管理者のみ可能です。']);
        }
        
        $request->validate([
            'role' => 'required|in:admin,member',
        ]);

        // Prevent demoting the last admin
        if ($user->role === 'admin' && $request->role !== 'admin' && User::where('role', 'admin')->where('is_active', true)->count() <= 1) {
            return Redirect::back()->withErrors(['message' => '最後の管理者の権限は変更できません。']);
        }

        $oldRole = $user->role;
        $user->update(['role' => $request->role]);

        \App\Models\UserLog::create([
            'user_id' => $user->id,
            'changed_by' => auth()->id(),
            'type' => 'role',
            'old_value' => $oldRole,
            'new_value' => $request->role,
            'reason' => 'Admin updated role',
        ]);

        return Redirect::back();
    }

    public function logs(User $user)
    {
        return response()->json($user->logs);
    }
}
