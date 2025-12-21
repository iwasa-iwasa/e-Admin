<?php

namespace App\Http\Controllers;

use App\Models\TrashAutoDeleteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TrashAutoDeleteController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        
        $logs = \App\Models\TrashAutoDeleteLog::where('user_id', $userId)
            ->orderBy('executed_at', 'desc')
            ->take(10)
            ->get();
        
        return Inertia::render('TrashAutoDelete', [
            'currentSetting' => TrashAutoDeleteSetting::getUserPeriod($userId),
            'options' => [
                'disabled' => '自動削除しない',
                '1_month' => '1ヶ月後',
                '3_months' => '3ヶ月後', 
                '6_months' => '6ヶ月後',
                '1_year' => '1年後'
            ],
            'logs' => $logs
        ]);
    }
    
    public function update(Request $request)
    {
        $request->validate([
            'period' => 'required|in:disabled,1_month,3_months,6_months,1_year'
        ]);
        
        TrashAutoDeleteSetting::setUserPeriod(Auth::id(), $request->period);
        
        return back()->with('success', '自動削除設定を更新しました。');
    }
}