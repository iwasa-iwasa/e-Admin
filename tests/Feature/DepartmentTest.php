<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Department;
use App\Models\Calendar;
use App\Models\Event;
use App\Enums\EventCategory;
use App\Enums\EventImportance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // デフォルトカレンダーを作成
        Calendar::forceCreate([
            'calendar_id' => 1,
            'calendar_name' => 'Default',
            'calendar_type' => 'shared',
            'owner_type' => 'company',
            'owner_id' => null,
        ]);
    }

    /**
     * 部署作成時にカレンダーが自動生成されることをテスト
     */
    public function test_department_creation_auto_creates_calendar(): void
    {
        $department = Department::create(['name' => 'テスト部署']);

        // カレンダーが自動生成されていることを確認
        $this->assertDatabaseHas('calendars', [
            'calendar_name' => 'テスト部署カレンダー',
            'calendar_type' => 'shared',
            'owner_type' => 'department',
            'owner_id' => $department->id,
        ]);
    }

    /**
     * 部署名変更時にカレンダー名も更新されることをテスト
     */
    public function test_department_name_change_updates_calendar_name(): void
    {
        $department = Department::create(['name' => '旧部署名']);

        $department->update(['name' => '新部署名']);

        $this->assertDatabaseHas('calendars', [
            'calendar_name' => '新部署名カレンダー',
            'owner_type' => 'department',
            'owner_id' => $department->id,
        ]);
    }

    /**
     * 管理者のみが部署を作成できることをテスト
     */
    public function test_admin_can_create_department(): void
    {
        // 部署を事前作成しておく
        $dept = Department::create(['name' => '総務部']);

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'admin',
            'role_type' => 'company_admin',
            'department_id' => $dept->id,
        ]);

        $newMember = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'user',
            'role_type' => 'member',
            'department_id' => $dept->id,
        ]);

        $response = $this->actingAs($admin)->post('/admin/departments', [
            'name' => '営業部',
            'admin_user_id' => $newMember->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('departments', ['name' => '営業部']);
    }

    /**
     * 一般ユーザーが部署を作成できないことをテスト
     */
    public function test_member_cannot_create_department(): void
    {
        $dept = Department::create(['name' => '総務部']);

        $member = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'user',
            'role_type' => 'member',
            'department_id' => $dept->id,
        ]);

        $response = $this->actingAs($member)->post('/admin/departments', [
            'name' => '営業部',
            'admin_user_id' => $member->id,
        ]);

        // adminミドルウェアで弾かれる
        $response->assertStatus(403);
    }

    /**
     * 部署APIで一覧取得できることをテスト
     */
    public function test_department_api_returns_active_departments(): void
    {
        $dept1 = Department::create(['name' => '総務部', 'is_active' => true]);
        $dept2 = Department::create(['name' => '営業部', 'is_active' => true]);
        $dept3 = Department::create(['name' => '廃止部署', 'is_active' => false]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'department_id' => $dept1->id,
        ]);

        $response = $this->actingAs($user)->get('/api/departments');

        $response->assertStatus(200);
        $data = $response->json();

        // アクティブな部署のみ返ること
        $this->assertCount(2, $data);
        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('総務部', $names);
        $this->assertContains('営業部', $names);
        $this->assertNotContains('廃止部署', $names);
    }
}
