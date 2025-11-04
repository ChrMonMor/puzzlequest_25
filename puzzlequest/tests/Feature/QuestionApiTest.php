<?php

namespace Tests\Feature;

use Tests\ApiTestCase;
use App\Models\User;
use App\Models\Run;
use App\Models\Flag;
use App\Models\Question;
use App\Models\QuestionType;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class QuestionApiTest extends ApiTestCase
{
    
    use RefreshDatabase;

    protected ?User $user = null;
    protected ?string $token = null;
    protected ?Run $run = null;
    protected ?Flag $flag = null;
    protected ?QuestionType $questionType = null;
    protected $withoutMiddleware = true;


    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = JWTAuth::fromUser($this->user);

        $this->run = Run::factory()->create([
            'user_id' => $this->user->user_id, // same user
        ]); 

        // Related models
        $this->flag = Flag::factory()->create(['run_id' => $this->run->run_id]);
        $this->questionType = QuestionType::factory()->create([
            'question_type_name' => 'Default',
        ]);

    }

    /** @test */
    public function user_can_create_question()
    {
        $payload = [
            'run_id' => $this->run->run_id,
            'flag_id' => $this->flag->flag_id,
            'question_type' => $this->questionType->question_type_id,
            'question_text' => 'What is 2 + 2?',
            'question_answer' => '4',
        ];

        $response = $this->withToken($this->token)->postJson('/api/questions', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'question' => [
                         'question_id',
                         'run_id',
                         'flag_id',
                         'question_type',
                         'question_text',
                         'question_answer',
                     ],
                 ]);

        $this->assertDatabaseHas('questions', [
            'question_text' => 'What is 2 + 2?',
            'question_answer' => '4',
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

        $response = $this->withToken($this->token)->putJson("/api/questions/{$question->question_id}", [
            'question_text' => 'Updated question text?',
            'question_answer' => 'Updated answer',
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['question_text' => 'Updated question text?']);

        $this->assertDatabaseHas('questions', [
            'question_id' => $question->question_id,
            'question_text' => 'Updated question text?',
        ]);
    }
    
    /** @test */
    public function user_can_create_question_with_options()
    {
        $this->authenticate();
        $run = Run::factory()->create(['user_id' => $this->user->user_id]);
        $flag = Flag::factory()->create(['run_id' => $run->run_id]);

        $payload = [
            'run_id' => $run->run_id,
            'flag_id' => $flag->flag_id,
            'question_type' => 1,
            'question_text' => 'Sample Question',
            'options' => [
                ['question_option_text' => 'A'],
                ['question_option_text' => 'B'],
            ]
        ];

        $response = $this->withToken()->postJson("/api/questions", $payload);

        $response->assertStatus(201)
                 ->assertJsonFragment(['question_text' => 'Sample Question']);

        $this->assertDatabaseCount('question_options', 2);
    }

    
    /** @test */
    public function user_can_bulk_update_questions()
    {
        $questions = Question::factory()->count(3)->create([
            'run_id' => $this->run->run_id,
            'flag_id' => $this->flag->flag_id,
            'question_type' => $this->questionType->question_type_id,
        ]);

        $payload = $questions->map(fn($q) => [
            'question_id' => $q->question_id,
            'question_text' => 'Bulk updated text',
        ])->toArray();

        $response = $this->withToken($this->token)->putJson('/api/questions/bulk-update', $payload);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Questions updated successfully']);

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
        $this->authenticate();
        $run = Run::factory()->create(['user_id' => $this->user->user_id]);
        $flag = Flag::factory()->create(['run_id' => $run->run_id]);
        $questions = Question::factory()->count(2)->create(['run_id' => $run->run_id, 'flag_id' => $flag->flag_id]);

        $ids = $questions->pluck('question_id')->toArray();

        $response = $this->withToken()->deleteJson("/api/questions/bulk", ['ids' => $ids]);

        $response->assertStatus(200);

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

        $response = $this->getJson("/api/runs/{$this->run->run_id}/questions");

        $response->assertStatus(200)
                 ->assertJsonCount(3);
    }
}
