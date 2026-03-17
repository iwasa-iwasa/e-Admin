<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Event;
use App\Models\SharedNote;
use App\Models\Reminder;
use App\Models\Department;
use App\Models\Calendar;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $memberId = $request->query('member_id');
        $departmentId = $request->query('department_id');
        $showCompany = $request->query('show_company');

        // Fetch all events from the shared calendar
        $eventsQuery = Event::with(['creator', 'participants'])
            ->orderBy('start_date');

        // 部署フィルターを適用
        if ($departmentId) {
            // 特定部署のイベントのみ表示
            $eventsQuery->where(function($query) use ($departmentId) {
                $query->where(function($subQuery) use ($departmentId) {
                    // 部署イベントでかつ指定部署のもの
                    $subQuery->where('visibility_type', 'department')
                             ->where('owner_department_id', $departmentId);
                })->orWhere('visibility_type', 'public'); // 全社イベントも含める
            });
        } elseif ($showCompany) {
            // 全社イベントのみ表示
            $eventsQuery->where('visibility_type', 'public');
        }

        // メンバーフィルターを適用（部署フィルターと併用可能）
        if ($memberId) {
            $eventsQuery->whereHas('participants', function ($query) use ($memberId) {
                $query->where('users.id', $memberId);
            });
        }

        $events = $eventsQuery->get();

        $notesQuery = SharedNote::with(['author', 'participants'])
            ->where(function($query) use ($user) {
                $query->where('author_id', $user->id)
                      ->orWhereHas('participants', function($q) use ($user) {
                          $q->where('users.id', $user->id);
                      })
                      ->orWhereDoesntHave('participants');
            });

        if ($memberId) {
            $notesQuery->where(function($query) use ($memberId) {
                $query->whereHas('participants', function ($q) use ($memberId) {
                    $q->where('users.id', $memberId);
                })->orWhereDoesntHave('participants');
            });
        }

        $notes = $notesQuery->orderBy('updated_at', 'desc')->get();

        // Get IDs of notes pinned by the current user
        $pinnedNoteIds = $user->pinnedNotes()->pluck('shared_notes.note_id')->all();

        // Add is_pinned attribute to each note
        $notes->each(function ($note) use ($pinnedNoteIds) {
            $note->is_pinned = in_array($note->note_id, $pinnedNoteIds);
        });

        // Sort by is_pinned desc (pinned notes first), then by updated_at (already sorted by DB)
        $sortedNotes = $notes->sortByDesc('is_pinned');

        $reminders = $user->reminders()
            ->with('tags')
            ->orderByRaw('CASE WHEN deadline_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('deadline_date')
            ->orderBy('deadline_time')
            ->get();

        $teamMembers = \App\Models\User::where('is_active', true)
            ->select('id', 'name', 'email', 'department_id', 'role', 'role_type')
            ->get();
        
        // 部署情報を取得（ユーザーとのリレーションも含めて）
        $departmentsData = Department::where('is_active', true)
            ->with(['users' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($dept) {
                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'is_active' => $dept->is_active,
                    'created_at' => $dept->created_at,
                    'updated_at' => $dept->updated_at,
                    'users' => $dept->users->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'department_id' => $user->department_id,
                            'role' => $user->role,
                            'role_type' => $user->role_type,
                        ];
                    })->toArray()
                ];
            })
            ->toArray();
            
        // デバッグ用ログ
        \Log::info('DashboardController departments:', $departmentsData);
        
        // カレンダー情報を取得
        $calendars = Calendar::orderBy('calendar_name')->get();
        
        // ユーザーのデフォルトカレンダーを決定
        $defaultCalendarId = null;
        if ($user->department_id) {
            // 所属部署のカレンダーを優先
            $departmentCalendar = $calendars->where('owner_type', 'department')
                                           ->where('owner_id', $user->department_id)
                                           ->first();
            if ($departmentCalendar) {
                $defaultCalendarId = $departmentCalendar->calendar_id;
            }
        }
        
        // 部署カレンダーがない場合は全社カレンダーを選択
        if (!$defaultCalendarId) {
            $companyCalendar = $calendars->where('owner_type', 'company')->first();
            if ($companyCalendar) {
                $defaultCalendarId = $companyCalendar->calendar_id;
            }
        }
        
        return Inertia::render('Dashboard', [
            'events' => $events,
            'sharedNotes' => $sortedNotes->values(),
            'personalReminders' => $reminders,
            'filteredMemberId' => $memberId ? (int)$memberId : null,
            'teamMembers' => $teamMembers,
            'totalUsers' => $teamMembers->count(),
            'defaultView' => auth()->user()->calendar_default_view ?? 'dayGridMonth',
            'departments' => $departmentsData,
            'calendars' => $calendars,
            'userDepartmentId' => $user->department_id,
            'userRoleType' => $user->role_type,
            'defaultCalendarId' => $defaultCalendarId,
            'selectedDepartmentId' => $departmentId ? (int)$departmentId : null,
            'showCompany' => $showCompany ? true : false,
        ]);
    }
}