<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Survey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SurveyTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_surveys_page()
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->get(route('surveys'));

        $response->assertStatus(200);
    }

    public function test_can_create_survey()
    {
        $user = User::factory()->create(['is_active' => true]);

        $surveyData = [
            'title' => 'Test Survey',
            'description' => 'Test Description',
            'deadline' => now()->addDays(7)->toDateTimeString(),
            'questions' => [
                [
                    'question' => 'Test Question 1?',
                    'type' => 'text',
                    'required' => true,
                    'options' => [],
                ],
                [
                    'question' => 'Test Question 2?',
                    'type' => 'single',
                    'required' => false,
                    'options' => ['Option 1', 'Option 2'],
                ]
            ],
            'respondents' => [$user->id],
        ];

        $response = $this->actingAs($user)->post(route('surveys.store'), $surveyData);

        $response->assertRedirect(route('surveys'));
        $this->assertDatabaseHas('surveys', [
            'title' => 'Test Survey',
            'description' => 'Test Description',
        ]);
        $this->assertDatabaseHas('survey_questions', ['question_text' => 'Test Question 1?']);
    }

    public function test_can_update_survey()
    {
        $user = User::factory()->create(['is_active' => true]);
        $survey = Survey::create([
            'title' => 'Original Title',
            'description' => 'Original Description',
            'created_by' => $user->id,
            'deadline_date' => now()->addDays(7)->format('Y-m-d'),
            'deadline_time' => '12:00:00',
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'description' => 'Updated Description',
            'deadline' => now()->addDays(10)->toDateTimeString(),
            'questions' => [
                 [
                    'question' => 'Updated Question 1?',
                    'type' => 'text',
                    'required' => true,
                    'options' => [],
                ]
            ],
            'respondents' => [$user->id],
        ];

        $response = $this->actingAs($user)->put(route('surveys.update', $survey), $updateData);

        $response->assertRedirect(route('surveys'));
        $this->assertDatabaseHas('surveys', ['title' => 'Updated Title']);
    }

    public function test_can_delete_survey()
    {
        $user = User::factory()->create(['is_active' => true]);
        $survey = Survey::create([
            'title' => 'To Be Deleted',
            'created_by' => $user->id,
            'is_deleted' => false,
        ]);

        $response = $this->actingAs($user)->delete(route('surveys.destroy', $survey));

        $response->assertRedirect(route('surveys'));
        $this->assertDatabaseHas('surveys', [
            'survey_id' => $survey->survey_id,
            'is_deleted' => true,
        ]);
        $this->assertSoftDeleted('surveys', ['survey_id' => $survey->survey_id]); // If soft deletes are used, otherwise check is_deleted flag logic
    }
}
