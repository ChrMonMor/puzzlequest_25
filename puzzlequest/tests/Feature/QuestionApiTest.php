<?php

namespace Tests\Feature;

use Tests\ApiTestCase;
use App\Models\User;
use App\Models\Run;
use App\Models\Flag;
use App\Models\Question;
use App\Models\QuestionType;
use App\Models\QuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QuestionApiTest extends ApiTestCase
{
    use RefreshDatabase;

    protected ?Run $run = null;
    protected ?Flag $flag = null;
    protected ?QuestionType $questionType = null;


    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticate();

        $this->run = Run::factory()->create([
            'user_id' => $this->user->user_id,
        ]);

        // Related models
        $this->flag = Flag::factory()->create(['run_id' => $this->run->run_id]);
        
        if (!QuestionType::first()) {
            $this->questionType = QuestionType::factory()->create([
                'question_type_name' => 'Default',
            ]);
        } else {
            $this->questionType = QuestionType::first();
        }
    }

    /** @test */
    public function user_can_create_question()
    {
        $payload = [
            'run_id' => $this->run->run_id,
            'flag_id' => $this->flag->flag_id,
            'question_type' => $this->questionType->question_type_id,
            'question_text' => 'What is 2 + 2?',
        ];

        $response = $this->withToken()->postJson('/api/questions', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'question' => [
                         'question_id',
                         'run_id',
                         'flag_id',
                         'question_type',
                         'question_text',
                     ],
                 ]);

        $this->assertDatabaseHas('questions', [
            'question_text' => 'What is 2 + 2?',
        ]);
    }
    /** @test */
    public function user_can_update_question()
    {
        $question = Question::factory()->create([
            'run_id' => $this->run->run_id,
            'flag_id' => $this->flag->flag_id,
            'question_type' => $this->questionType->question_type_id,
        ]);

        $response = $this->withToken()->putJson("/api/questions/{$question->question_id}", [
            'question_text' => 'Updated question text?',
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['question_text' => 'Updated question text?']);

        $this->assertDatabaseHas('questions', [
            'question_id' => $question->question_id,
            'question_text' => 'Updated question text?',
        ]);
    }
    /** @test */
    public function user_can_bulk_update_questions()
    {
        $questions = Question::factory()->count(3)->create([
            'run_id' => $this->run->run_id,
            'flag_id' => $this->flag->flag_id,
            'question_type' => $this->questionType->question_type_id,
        ]);

        $payload = [
            'questions' => $questions->map(fn($q) => [
                'question_id' => $q->question_id,
                'question_text' => 'Bulk updated text',
            ])->toArray()
        ];

        $response = $this->withToken()->putJson("/api/runs/{$this->run->run_id}/questions/bulk", $payload);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Questions updated']);

        foreach ($questions as $q) {
            $this->assertDatabaseHas('questions', [
                'question_id' => $q->question_id,
                'question_text' => 'Bulk updated text',
            ]);
        }
    }

    /** @test */
    public function user_can_bulk_delete_questions()
    {
        $questions = Question::factory()->count(2)->create([
            'run_id' => $this->run->run_id,
            'flag_id' => $this->flag->flag_id,
            'question_type' => $this->questionType->question_type_id,
        ]);

        $ids = $questions->pluck('question_id')->toArray();

        $response = $this->withToken()->deleteJson("/api/runs/{$this->run->run_id}/questions/bulk", ['question_ids' => $ids]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message']);

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('questions', ['question_id' => $id]);
        }
    }

    /** @test */
    public function anyone_can_view_questions_for_a_run()
    {
        $questions = Question::factory()->count(3)->create([
            'run_id' => $this->run->run_id,
            'flag_id' => $this->flag->flag_id,
            'question_type' => $this->questionType->question_type_id,
        ]);

        $response = $this->getJson("/api/questions?run_id={$this->run->run_id}");

        $response->assertStatus(200);
    }
}
