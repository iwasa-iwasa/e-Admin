<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;
use App\Models\User;
use App\Models\Survey;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        
        $sharedData = [
            'auth' => [
                'user' => $user,
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
        
        // ユーザーが認証されている場合のみ追加データを取得
        if ($user) {
            $teamMembers = User::where('is_active', true)->get();
            
            // 未回答のアクティブなアンケート件数を取得（期限切れでないもののみ）
            $unansweredSurveysCount = Survey::where('is_active', true)
                ->where('is_deleted', false)
                ->where(function ($query) {
                    $query->whereNull('deadline_date')
                          ->orWhere('deadline_date', '>=', now()->toDateString());
                })
                ->whereDoesntHave('responses', function ($query) use ($user) {
                    $query->where('respondent_id', $user->id);
                })
                ->count();
            
            $sharedData['teamMembers'] = $teamMembers;
            $sharedData['totalUsers'] = $teamMembers->count();
            $sharedData['unansweredSurveysCount'] = $unansweredSurveysCount;
        }

        return array_merge(parent::share($request), $sharedData);
    }
}
