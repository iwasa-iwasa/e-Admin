<?php

namespace Tests\Feature;

use App\Enums\EventCategory;
use App\Enums\EventImportance;
use App\Models\User;
use App\Models\Event;
use App\Models\Calendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Carbon\Carbon;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed Calendar
        Calendar::forceCreate([
            'calendar_id' => 1, 
            'calendar_name' => 'Default',
            'calendar_type' => 'shared'
        ]);
    }

    public function test_calendar_page_is_displayed()
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);

        $response = $this->actingAs($user)->get('/calendar');
        $response->assertStatus(200);
    }

    public function test_event_can_be_created()
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);

        $eventData = [
            'title' => 'Test Event',
            'date_range' => [
                now()->format('Y-m-d'),
                now()->addDay()->format('Y-m-d'),
            ],
            'is_all_day' => false,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'description' => 'Test Description',
            'location' => 'Meeting Room A',
            'category' => EventCategory::MEETING->value,
            'importance' => EventImportance::HIGH->value,
        ];

        $response = $this->actingAs($user)->post('/events', $eventData);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('events', [
            'title' => 'Test Event',
            'location' => 'Meeting Room A',
            'category' => '会議',
            'created_by' => $user->id,
        ]);
    }

    public function test_event_validation_fails_with_invalid_data()
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);

        // Missing required fields
        $response = $this->actingAs($user)->post('/events', [
            'title' => '',
        ]);

        $response->assertSessionHasErrors(['title', 'date_range', 'category', 'importance']);
    }

    public function test_event_can_be_updated()
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $calendar = Calendar::first();
        
        $event = Event::create([
            'calendar_id' => $calendar->calendar_id,
            'title' => 'Old Title',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'is_all_day' => true,
            'category' => EventCategory::OTHER->value,
            'importance' => EventImportance::LOW->value,
            'created_by' => $user->id,
        ]);

        $updateData = [
            'title' => 'New Title',
            'date_range' => [
                now()->format('Y-m-d'),
                now()->format('Y-m-d'),
            ],
            'is_all_day' => true,
            'category' => EventCategory::WORK->value,
            'importance' => EventImportance::MEDIUM->value,
            'location' => 'New Location',
            'description' => 'New Description',
            'progress' => 50,
        ];

        $response = $this->actingAs($user)->put("/events/{$event->event_id}", $updateData);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        
        $this->assertDatabaseHas('events', [
            'event_id' => $event->event_id,
            'title' => 'New Title',
            'category' => '業務',
        ]);
    }

    public function test_event_can_be_deleted()
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'is_active' => true]);
        $calendar = Calendar::first();
        
        $event = Event::create([
            'calendar_id' => $calendar->calendar_id,
            'title' => 'Delete Me',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
            'is_all_day' => true,
            'category' => EventCategory::OTHER->value,
            'importance' => EventImportance::LOW->value,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->delete("/events/{$event->event_id}");

        $response->assertRedirect();

        $this->assertSoftDeleted('events', [
            'event_id' => $event->event_id,
        ]);

        // Check trash item created
        $this->assertDatabaseHas('trash_items', [
            'item_type' => 'event',
            'item_id' => $event->event_id,
        ]);
    }
}
