<?php

namespace Tests\Feature;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SurveyRefactorVerifyTest extends TestCase
{
    // NO RefreshDatabase, as we want to use the existing seeded data or just clean up manually,
    // OR use Transaction trait. Using RefreshDatabase might wipe the schema changes if not careful with migration state.
    // Given I just migrated, I should be careful. 
    // Usually RefreshDatabase is fine if migrations are correct.
    use RefreshDatabase;

    protected $user;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed users if needed or just create generic ones
        $this->user = User::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_create_survey_with_questions()
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('surveys.store'), [
            'title' => 'Test Survey',
            'deadline' => now()->addDays(7)->toDateTimeString(),
            'questions' => [
                [
                    'question' => 'Q1 Text?',
                    'type' => 'text',
                    'required' => true,
                    'options' => []
                ],
                [
                    'question' => 'Q2 Choice?',
                    'type' => 'single',
                    'required' => true,
                    'options' => ['A', 'B']
                ]
            ],
            'respondents' => [$this->user->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('surveys', ['title' => 'Test Survey', 'version' => 1]);
        $survey = Survey::where('title', 'Test Survey')->first();
        $this->assertEquals(2, $survey->questions()->count());
        $this->assertEquals('single_choice', $survey->questions->last()->question_type);
    }

    public function test_can_save_draft_answer()
    {
        $survey = Survey::create([
             'title' => 'Draft Test Survey',
             'created_by' => $this->admin->id,
             'version' => 1,
             'is_active' => true,
             'deadline_date' => now()->addWeek()
        ]);
        
        $question = $survey->questions()->create([
             'question_text' => 'Draft Q',
             'question_type' => 'text',
             'display_order' => 1
        ]);
        
        $this->actingAs($this->user);
        $this->withoutExceptionHandling();

        // Draft submission
        $response = $this->post(route('surveys.submit', $survey->survey_id), [
            'answers' => [
                $question->question_id => 'Draft Answer'
            ],
            'status' => 'draft'
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('survey_responses', [
            'survey_id' => $survey->survey_id,
            'respondent_id' => $this->user->id,
            'status' => 'draft',
            'survey_version' => 1
        ]);
        
        $surveyResponse = SurveyResponse::where('survey_id', $survey->survey_id)->first();
        $this->assertEquals(['Draft Answer'], array_values($surveyResponse->answers));
    }

    public function test_updating_survey_questions_increments_version()
    {
        $this->actingAs($this->admin);
        $survey = Survey::create([
            'title' => 'V1 Survey', 
            'created_by' => $this->admin->id,
            'version' => 1,
            'deadline_date' => now()->addDay(),
            'is_active' => true
        ]);
        $q1 = $survey->questions()->create([
            'question_text' => 'Old Q',
            'question_type' => 'text',
            'display_order' => 1,
            'is_required' => true
        ]);

        // Create a response (draft)
        SurveyResponse::create([
            'survey_id' => $survey->survey_id,
            'respondent_id' => $this->user->id,
            'answers' => [$q1->question_id => 'Ans'],
            'status' => 'draft',
            'survey_version' => 1
        ]);

        // Update Survey: Change Question Text
        $response = $this->put(route('surveys.update', $survey->survey_id), [
            'title' => 'V1 Survey',
            'deadline' => now()->addDay()->toDateTimeString(),
            'questions' => [
                [
                    'question_id' => $q1->question_id,
                    'question' => 'New Q Text', // Changed
                    'type' => 'text',
                    'required' => true,
                    'options' => []
                ]
            ],
            'confirm_reset' => false // Should normally trigger warning if not force
        ]);
        
        // Controller returns error if internal check fails, OR validates. 
        // In my logic: $questionsChanged is true. $hasResponses is true. 
        // So it should redirect back with 'requires_confirmation'.
        // But Inertia testing handles sessions.
        
        $response->assertSessionHasErrors(['requires_confirmation']);
        
        // Now force update
        $response = $this->put(route('surveys.update', $survey->survey_id), [
            'title' => 'V1 Survey',
            'deadline' => now()->addDay()->toDateTimeString(),
            'questions' => [
                [
                    'question_id' => $q1->question_id,
                    'question' => 'New Q Text',
                    'type' => 'text',
                    'required' => true,
                    'options' => []
                ]
            ],
            'confirm_reset' => true
        ]);
        
        $response->assertRedirect();
        
        $survey->refresh();
        $this->assertEquals(2, $survey->version); // Version incremented
        $this->assertEquals('New Q Text', $survey->questions->first()->question_text);
    }
}
