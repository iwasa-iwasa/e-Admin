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
        
        // ユーザー情報をロードする際にdepartmentリレーションも含める
        if ($user) {
            $user->load('department');
        }
        
        $sharedData = [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'department_id' => $user->department_id,
                    'role' => $user->role,
                    'role_type' => $user->role_type,
                    'is_active' => $user->is_active,
                    'email_verified_at' => $user->email_verified_at,
                ] : null,
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'conflict' => $request->session()->get('conflict'),
            ],
        ];
        
        // ユーザーが認証されている場合のみ追加データを取得
        if ($user) {
            // ユーザーの権限に応じてメンバーを制限
            if ($user->role_type === 'company_admin') {
                // 全社管理者は全メンバーを表示
                $teamMembers = User::where('is_active', true)
                    ->with('department')
                    ->select('id', 'name', 'email', 'department_id', 'role', 'role_type')
                    ->get();
            } else {
                // 部署管理者やメンバーは自部署のみ
                $teamMembers = User::where('is_active', true)
                    ->where('department_id', $user->department_id)
                    ->with('department')
                    ->select('id', 'name', 'email', 'department_id', 'role', 'role_type')
                    ->get();
            }
            
            // 部署データをusersリレーション付きで取得
            $departments = \App\Models\Department::where('is_active', true)
                ->with(['users' => function($query) {
                    $query->where('is_active', true)
                          ->select('id', 'name', 'email', 'department_id', 'role', 'role_type');
                }])
                ->orderBy('name')
                ->get()
                ->map(function($dept) {
                    return [
                        'id' => $dept->id,
                        'name' => $dept->name,
                        'users' => $dept->users->toArray()
                    ];
                });
            
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
            $sharedData['departments'] = $departments;
            $sharedData['totalUsers'] = $teamMembers->count();
            $sharedData['unansweredSurveysCount'] = $unansweredSurveysCount;
        }

        return array_merge(parent::share($request), $sharedData);
    }
}
