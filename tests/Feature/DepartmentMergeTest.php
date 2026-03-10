<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Department;
use App\Models\Event;
use App\Models\Calendar;
use App\Models\SharedNote;
use App\Services\DepartmentMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentMergeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create root default calendar if necessary
        Calendar::forceCreate([
            'calendar_id' => 1,
            'calendar_name' => 'Default',
            'calendar_type' => 'shared',
            'owner_type' => 'company',
            'owner_id' => null,
        ]);
    }

    public function test_departments_can_be_merged_successfully()
    {
        // 統合元の部署1・部署2
        $sourceDept1 = Department::create(['name' => '旧営業1課']);
        $sourceDept2 = Department::create(['name' => '旧営業2課']);
        // 統合先の部署
        $targetDept = Department::create(['name' => '新営業部']);

        // 部署のカレンダーを取得（DepartmentObserverにより自動生成されている想定）
        $targetCalendar = Calendar::where('owner_type', 'department')->where('owner_id', $targetDept->id)->first();
        if (!$targetCalendar) {
            $targetCalendar = Calendar::create([
                'calendar_name' => $targetDept->name . 'カレンダー',
                'calendar_type' => 'shared',
                'owner_type' => 'department',
                'owner_id' => $targetDept->id,
            ]);
        }

        // ユーザーの作成
        $user1 = User::factory()->create(['department_id' => $sourceDept1->id, 'is_active' => true]);
        $user2 = User::factory()->create(['department_id' => $sourceDept2->id, 'is_active' => true]);

        // 旧部署に紐づくイベント作成
        Event::create([
            'calendar_id' => 1,
            'title' => '部署1のイベント',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'is_all_day' => true,
            'owner_department_id' => $sourceDept1->id,
            'category' => '会議',
            'importance' => '中',
            'created_by' => $user1->id,
        ]);

        // 共有メモ作成
        SharedNote::create([
            'title' => '部署2のメモ',
            'content' => 'テスト',
            'author_id' => $user2->id,
            'owner_department_id' => $sourceDept2->id,
            'color' => 'yellow',
            'priority' => 'medium',
        ]);

        // サービスのインスタンス化と実行
        $mergeService = app(DepartmentMergeService::class);
        
        $this->actingAs($user1);
        $mergeService->mergeDepartments([$sourceDept1->id, $sourceDept2->id], $targetDept->id, '組織再編のため');

        // 検証
        // 1. ユーザーの所属が変わっているか
        $this->assertEquals($targetDept->id, $user1->fresh()->department_id);
        $this->assertEquals($targetDept->id, $user2->fresh()->department_id);

        // 2. 廃止部署が非アクティブになっているか
        $this->assertFalse((bool)$sourceDept1->fresh()->is_active);
        $this->assertFalse((bool)$sourceDept2->fresh()->is_active);

        // 3. データ（イベント）が移行されているか
        $this->assertDatabaseHas('events', [
            'title' => '部署1のイベント',
            'owner_department_id' => $targetDept->id,
        ]);

        // 4. データ（メモ）が移行されているか
        $this->assertDatabaseHas('shared_notes', [
            'title' => '部署2のメモ',
            'owner_department_id' => $targetDept->id,
        ]);
        
        // 5. カレンダー自体の統廃合が確認できるか
        // 5. カレンダー自体の統廃合が確認できるか（統合元のカレンダーが削除されていること）
        $this->assertDatabaseMissing('calendars', [
            'owner_type' => 'department',
            'owner_id' => $sourceDept1->id,
            'deleted_at' => null // 物理削除またはソフトデリートされていること
        ]);
    }
}
