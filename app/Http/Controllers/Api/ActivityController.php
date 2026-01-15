<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecentActivity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * Track user activity.
     */
    public function track(Request $request)
    {
        RecentActivity::track(
            auth()->id(),
            $request->input('type'),
            $request->input('id')
        );

        return response()->json(['success' => true]);
    }
}
