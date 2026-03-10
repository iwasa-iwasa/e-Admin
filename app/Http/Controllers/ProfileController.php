<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'departments' => \App\Models\Department::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $oldDepartmentId = $user->department_id;
        $newDepartmentId = $validated['department_id'];
        
        // 部署変更の場合は異動処理を実行
        if ($oldDepartmentId != $newDepartmentId) {
            $transferService = app(\App\Services\UserDepartmentTransferService::class);
            $result = $transferService->transferDepartment($user, $newDepartmentId);
            
            if (isset($result['requires_confirmation'])) {
                return back()->with('transfer_confirmation', $result);
            }
        }
        
        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'department_id' => $newDepartmentId,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
