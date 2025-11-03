<?php

namespace Tests\Feature;

use Tests\ApiTestCase;
use App\Models\Run;
use App\Models\Flag;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QuestionApiTest extends ApiTestCase
{
    
    use RefreshDatabase;
    
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
}
