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
        return Inertia::render('Admin/Users/Index', [
            'users' => \App\Data\UserData::collect(User::orderBy('created_at', 'desc')->get()),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
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
