<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckDepartmentAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            abort(401, '認証されていません。');
        }

        // 部署管理者(department_admin)または全社管理者(company_admin)のみ許可
        if (!in_array($user->role_type, ['department_admin', 'company_admin'])) {
            return response()->json(['message' => 'この操作には管理者権限が必要です。'], 403);
        }

        return $next($request);
    }
}
