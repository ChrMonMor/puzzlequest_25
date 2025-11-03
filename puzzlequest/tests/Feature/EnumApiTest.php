<?php

namespace Tests\Feature;

use Tests\ApiTestCase;
use App\Models\RunType;
use App\Models\QuestionType;

class EnumApiTest extends ApiTestCase
{
    /** @test */
    public function user_can_fetch_run_types()
    {
        RunType::factory()->count(3)->create();

        $response = $this->getJson('/api/run-types');

        $response->assertStatus(200)->assertJsonCount(3);
    }

    /** @test */
    public function user_can_fetch_question_types()
    {
        QuestionType::factory()->count(2)->create();

        $response = $this->getJson('/api/question-types');

        $response->assertStatus(200)->assertJsonCount(2);
    }
}
