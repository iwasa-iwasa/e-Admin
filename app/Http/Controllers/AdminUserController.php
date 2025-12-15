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
            'users' => User::orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $user->update([
            'is_active' => false,
            'deactivated_at' => now(),
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

        return Redirect::back();
    }
}
