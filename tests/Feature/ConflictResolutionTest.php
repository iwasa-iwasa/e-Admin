<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Event;
use App\Models\Calendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConflictResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Calendar::forceCreate([
            'calendar_id' => 1,
            'calendar_name' => 'Default',
            'calendar_type' => 'shared'
        ]);
    }

    public function test_conflict_is_detected_on_outdated_version_update()
    {
        $user1 = User::factory()->create(['is_active' => true]);
        $user2 = User::factory()->create(['is_active' => true]);

        // 新規イベントの作成 (version = 1とする)
        $event = Event::create([
            'calendar_id' => 1,
            'title' => '競合テストイベント',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'is_all_day' => true,
            'category' => '会議',
            'importance' => '中',
            'visibility_type' => 'public',
            'created_by' => $user1->id,
            'version' => 1,
        ]);

        // Aさん (user1) が編集画面を開いている間に、先にBさん (user2) がタイトルを更新したとする
        // updateRouteをシミュレート
        $this->withoutExceptionHandling();
        $responseB = $this->actingAs($user2)->put(route('events.update', $event), [
            'title' => '競合テストイベント(Bさん更新済)',
            'date_range' => [now()->format('Y-m-d'), now()->format('Y-m-d')],
            'is_all_day' => true,
            'category' => '会議',
            'importance' => '中',
            'visibility_type' => 'public',
            'version' => 1, // Bさんはv1を持っていた
        ]);
        
        $responseB->assertSessionHasNoErrors();

        // Bさんの更新成功、Versionは2になる想定
        $currentEvent = $event->fresh();
        $this->assertEquals(2, $currentEvent->version);
        $this->assertEquals('競合テストイベント(Bさん更新済)', $currentEvent->title);

        // 次にAさん (user1) が、古いversion (v1) とまま、さらに更新しようとする
        $response = $this->actingAs($user1)->put(route('events.update', $event), [
            'title' => '競合テストイベント(Aさんの変更内容)',
            'date_range' => [now()->format('Y-m-d'), now()->format('Y-m-d')],
            'is_all_day' => true,
            'category' => '会議',
            'importance' => '中',
            'visibility_type' => 'public',
            'version' => 1, // ここがコンフリクトの原因
        ]);

        // コンフリクトエラーが発生し、セッションに結果が返されること
        $response->assertSessionHas('conflict');

        // イベントはBさんの更新状態(version 2)のまま維持されていること
        $this->assertEquals(2, $event->fresh()->version);
        $this->assertEquals('競合テストイベント(Bさん更新済)', $event->fresh()->title);

        // Aさんが強制的に保存 (force_update) した場合は書き換わることをテスト
        $forceResponse = $this->actingAs($user1)->put(route('events.update', $event), [
            'title' => '競合テストイベント(Aさんの強制保存)',
            'date_range' => [now()->format('Y-m-d'), now()->format('Y-m-d')],
            'is_all_day' => true,
            'category' => '会議',
            'importance' => '中',
            'visibility_type' => 'public',
            'version' => 1, 
            'force_update' => true, // 強制フラグを立てる
        ]);

        $forceResponse->assertSessionHasNoErrors();
        
        // Aさんの内容で上書きされ、Versionが3にインクリメントされていること
        $finalEvent = $event->fresh();
        $this->assertEquals(3, $finalEvent->version);
        $this->assertEquals('競合テストイベント(Aさんの強制保存)', $finalEvent->title);
    }
}
