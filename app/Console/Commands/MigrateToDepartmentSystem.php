<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\SharedNote;
use App\Models\Survey;
use Illuminate\Support\Facades\DB;

class MigrateToDepartmentSystem extends Command
{
    protected $signature = 'migrate:department-system';
    protected $description = '既存データを部署システムに移行（総務部カレンダーへ）';
    
    public function handle()
    {
        $this->info('🚀 部署システムへの移行を開始します...');
        
        try {
            DB::transaction(function() {
                $this->createInitialDepartments();
                $this->migrateCalendars();
                $this->migrateEvents();
                $this->migrateSharedNotes();
                $this->migrateSurveys();
            });
            
            $this->info('✅ 移行が完了しました！');
            $this->displaySummary();
        } catch (\Exception $e) {
            $this->error('❌ 移行に失敗しました: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function createInitialDepartments()
    {
        $this->info('📁 初期部署を作成中...');
        
        $departments = ['総務部', '営業部', '開発部'];
        
        foreach ($departments as $name) {
            DB::table('departments')->insertOrIgnore([
                'name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->info('   ✓ 部署作成完了: ' . implode(', ', $departments));
    }
    
    private function migrateCalendars()
    {
        $this->info('📅 既存カレンダーを総務部カレンダーに変換中...');
        
        $somubuDept = DB::table('departments')->where('name', '総務部')->first();
        
        // 既存の共有カレンダーを総務部カレンダーに変換
        $updated = DB::table('calendars')
            ->where('calendar_type', 'shared')
            ->whereNull('owner_type')
            ->update([
                'owner_type' => 'department',
                'owner_id' => $somubuDept->id,
            ]);
        
        $this->info("   ✓ {$updated}件のカレンダーを総務部カレンダーに変換");
        
        // 総務部カレンダーが存在しない場合は作成
        $somubuCalendar = DB::table('calendars')
            ->where('owner_type', 'department')
            ->where('owner_id', $somubuDept->id)
            ->first();
            
        if (!$somubuCalendar) {
            DB::table('calendars')->insert([
                'calendar_name' => '総務部カレンダー',
                'calendar_type' => 'shared',
                'owner_type' => 'department',
                'owner_id' => $somubuDept->id,
                'created_at' => now(),
            ]);
            $this->info('   ✓ 総務部カレンダーを作成');
        }
        
        // 全社カレンダーを作成（重複チェック）
        $companyCalendar = DB::table('calendars')
            ->where('owner_type', 'company')
            ->whereNull('owner_id')
            ->first();
            
        if (!$companyCalendar) {
            DB::table('calendars')->insert([
                'calendar_name' => '全社カレンダー',
                'calendar_type' => 'shared',
                'owner_type' => 'company',
                'owner_id' => null,
                'created_at' => now(),
            ]);
            $this->info('   ✓ 全社カレンダーを作成');
        }
    }
    
    private function migrateEvents()
    {
        $this->info('📌 イベントを移行中...');
        
        $somubuDept = DB::table('departments')->where('name', '総務部')->first();
        $somubuCalendar = DB::table('calendars')
            ->where('owner_type', 'department')
            ->where('owner_id', $somubuDept->id)
            ->first();
        
        $count = 0;
        
        DB::table('events')
            ->whereNull('owner_department_id')
            ->orderBy('event_id')
            ->chunk(100, function($events) use ($somubuDept, $somubuCalendar, &$count) {
                foreach ($events as $event) {
                    $creator = DB::table('users')->find($event->created_by);
                    $hasParticipants = DB::table('event_participants')
                        ->where('event_id', $event->event_id)
                        ->exists();
                    
                    DB::table('events')
                        ->where('event_id', $event->event_id)
                        ->update([
                            'calendar_id' => $somubuCalendar->calendar_id,
                            'owner_department_id' => $creator?->department_id ?? $somubuDept->id,
                            'visibility_type' => $hasParticipants ? 'custom' : 'public',
                            'version' => 0,
                        ]);
                    
                    $count++;
                }
            });
        
        $this->info("   ✓ {$count}件のイベントを移行");
    }
    
    private function migrateSharedNotes()
    {
        $this->info('📝 共有メモを移行中...');
        
        $somubuDept = DB::table('departments')->where('name', '総務部')->first();
        $count = 0;
        
        DB::table('shared_notes')
            ->whereNull('owner_department_id')
            ->orderBy('note_id')
            ->chunk(100, function($notes) use ($somubuDept, &$count) {
                foreach ($notes as $note) {
                    $author = DB::table('users')->find($note->author_id);
                    $hasParticipants = DB::table('shared_note_participants')
                        ->where('note_id', $note->note_id)
                        ->exists();
                    
                    DB::table('shared_notes')
                        ->where('note_id', $note->note_id)
                        ->update([
                            'owner_department_id' => $author?->department_id ?? $somubuDept->id,
                            'visibility_type' => $hasParticipants ? 'custom' : 'public',
                            'version' => 0,
                        ]);
                    
                    $count++;
                }
            });
        
        $this->info("   ✓ {$count}件の共有メモを移行");
    }
    
    private function migrateSurveys()
    {
        $this->info('📊 アンケートを移行中...');
        
        $somubuDept = DB::table('departments')->where('name', '総務部')->first();
        $count = 0;
        
        DB::table('surveys')
            ->whereNull('owner_department_id')
            ->orderBy('survey_id')
            ->chunk(100, function($surveys) use ($somubuDept, &$count) {
                foreach ($surveys as $survey) {
                    $creator = DB::table('users')->find($survey->created_by);
                    
                    DB::table('surveys')
                        ->where('survey_id', $survey->survey_id)
                        ->update([
                            'owner_department_id' => $creator?->department_id ?? $somubuDept->id,
                            'visibility_type' => 'public',
                            'version' => 0,
                        ]);
                    
                    $count++;
                }
            });
        
        $this->info("   ✓ {$count}件のアンケートを移行");
    }
    
    private function displaySummary()
    {
        $this->newLine();
        $this->info('📊 移行結果サマリー:');
        $this->table(
            ['項目', '件数'],
            [
                ['部署', DB::table('departments')->count()],
                ['カレンダー', DB::table('calendars')->count()],
                ['イベント', DB::table('events')->whereNotNull('owner_department_id')->count()],
                ['共有メモ', DB::table('shared_notes')->whereNotNull('owner_department_id')->count()],
                ['アンケート', DB::table('surveys')->whereNotNull('owner_department_id')->count()],
            ]
        );
    }
}
