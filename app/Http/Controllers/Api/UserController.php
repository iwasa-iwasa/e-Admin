<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Get list of active users for selection.
     */
    public function index()
    {
        return response()->json(
            User::where('is_active', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
        );
    }
}
