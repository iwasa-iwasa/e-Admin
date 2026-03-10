<?php

namespace App\Http\Controllers;

use App\Models\CompanyEventRequest;
use App\Models\Event;
use App\Models\Calendar;
use App\Models\User;
use App\Notifications\CompanyEventRequested;
use App\Notifications\CompanyEventApproved;
use App\Notifications\CompanyEventRejected;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CompanyEventRequestController extends Controller
{
    /**
     * 全社カレンダーへの予定追加を申請する
     */
    public function requestCompanyEvent(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'progress' => 'integer|min:0|max:100',
            'category' => 'nullable|integer',
            'importance' => 'nullable|integer',
            'start_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_date' => 'required|date|after_or_equal:start_date',
            'end_time' => 'nullable|date_format:H:i',
            'is_all_day' => 'boolean',
        ]);

        $companyEventRequest = CompanyEventRequest::create([
            'event_data' => $validated,
            'requested_by' => auth()->id(),
            'status' => 'pending',
            'requested_at' => now(),
        ]);
        
        // 全社管理者に通知
        $companyAdmins = User::where('role_type', 'company_admin')->get();
        Notification::send($companyAdmins, new CompanyEventRequested($companyEventRequest));

        return response()->json([
            'message' => '全社カレンダーへの追加を申請しました。',
            'data' => $companyEventRequest
        ], 201);
    }

    /**
     * 申請を承認する
     */
    public function approve(Request $request, CompanyEventRequest $companyEventRequest)
    {
        // 権限チェック（全社管理者のみ実行可能とするなど、必要に応じて実装。Policy推奨）
        
        if ($companyEventRequest->status !== 'pending') {
            return response()->json(['message' => 'この申請はすでに処理されています。'], 400);
        }

        DB::beginTransaction();
        try {
            // 全社カレンダーを取得
            $companyCalendar = Calendar::where('owner_type', 'company')->firstOrFail();
            
            $eventData = $companyEventRequest->event_data;
            
            // 予定を作成
            $event = Event::create(array_merge($eventData, [
                'calendar_id' => $companyCalendar->calendar_id,
                'created_by' => clone $companyEventRequest->requested_by,
                'owner_department_id' => null, // 全社カレンダーの予定は部署紐付けなし
                'visibility_type' => 'public',
                'version' => 1,
            ]));

            $companyEventRequest->update([
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            // 申請者に承認通知
            $companyEventRequest->requester->notify(new CompanyEventApproved($companyEventRequest, $event));

            // 承認時の AuditLog 記録
            \App\Models\AuditLog::create([
                'action' => 'company_event_approved',
                'user_id' => auth()->id(),
                'event_id' => $event->event_id,
                'calendar_id' => $companyCalendar->calendar_id,
                'details' => [
                    'request_id' => $companyEventRequest->id,
                    'event_title' => $event->title,
                ],
                'created_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => '申請を承認し、全社カレンダーに追加しました。',
                'data' => $event
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => '承認処理中にエラーが発生しました。', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 申請を却下する
     */
    public function reject(Request $request, CompanyEventRequest $companyEventRequest)
    {
        $validated = $request->validate([
            'review_comment' => 'required|string|max:1000',
        ]);

        if ($companyEventRequest->status !== 'pending') {
            return response()->json(['message' => 'この申請はすでに処理されています。'], 400);
        }

        $companyEventRequest->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_comment' => $validated['review_comment'],
        ]);

        // 申請者に却下通知
        $companyEventRequest->requester->notify(new CompanyEventRejected($companyEventRequest));

        return response()->json([
            'message' => '申請を却下しました。',
            'data' => $companyEventRequest
        ]);
    }
}
