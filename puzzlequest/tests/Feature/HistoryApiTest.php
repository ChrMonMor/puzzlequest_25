<?php

namespace Tests\Feature;

use Tests\ApiTestCase;
use App\Models\Run;
use App\Models\Flag;
use App\Models\History;
use App\Models\HistoryFlag;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HistoryApiTest extends ApiTestCase
{
    use RefreshDatabase;

    protected ?Run $run = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authenticate();

        $this->run = Run::factory()->create(['user_id' => $this->user->user_id]);
        Flag::factory()->count(3)->create(['run_id' => $this->run->run_id]);
    }

    /** @test */
    public function user_can_start_a_run()
    {
        $response = $this->withToken()->postJson("/api/history/run/{$this->run->run_id}/start");

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'history' => [
                         'history_id',
                         'run_id',
                         'user_id',
                         'history_start',
                     ],
                 ]);

        $this->assertDatabaseHas('histories', [
            'run_id' => $this->run->run_id,
            'user_id' => $this->user->user_id,
        ]);
    }

    /** @test */
    public function user_can_end_a_run()
    {
        $history = History::create([
            'user_id' => $this->user->user_id,
            'run_id' => $this->run->run_id,
            'history_start' => now(),
            'history_run_type' => 'test',
            'history_run_update' => now(),
        ]);

        $response = $this->withToken()->postJson("/api/history/run/{$history->history_id}/end");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Run ended successfully']);

        $this->assertDatabaseHas('histories', [
            'history_id' => $history->history_id,
        ]);

        $history->refresh();
        $this->assertNotNull($history->history_end);
    }

    /** @test */
    public function user_can_mark_flag_reached()
    {
        $history = History::create([
            'user_id' => $this->user->user_id,
            'run_id' => $this->run->run_id,
            'history_start' => now(),
            'history_run_type' => 'test',
            'history_run_update' => now(),
        ]);

        $flag = $this->run->flags->first();
        $historyFlag = HistoryFlag::create([
            'history_id' => $history->history_id,
            'history_flag_lat' => $flag->flag_lat,
            'history_flag_long' => $flag->flag_long,
        ]);

        $response = $this->withToken()->postJson("/api/history/run/{$history->history_id}/flag/{$historyFlag->history_flag_id}/reach", [
            'history_flag_point' => 100,
            'history_flag_distance' => 5.5,
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Flag marked as reached']);

        $this->assertDatabaseHas('history_flags', [
            'history_flag_id' => $historyFlag->history_flag_id,
            'history_flag_point' => 100,
        ]);

        $historyFlag->refresh();
        $this->assertNotNull($historyFlag->history_flag_reached);
    }

    /** @test */
    public function user_can_view_their_histories()
    {
            History::factory()->count(3)->create([
                'user_id' => $this->user->user_id,
                'run_id' => $this->run->run_id,
                    'history_run_update' => now(),
            ]);

        $response = $this->withToken()->getJson('/api/history');

        $response->assertStatus(200)
                 ->assertJsonCount(3);
    }

    /** @test */
    public function user_can_view_single_history()
    {
        $history = History::create([
            'user_id' => $this->user->user_id,
            'run_id' => $this->run->run_id,
            'history_start' => now(),
            'history_run_type' => 'test',
            'history_run_update' => now(),
        ]);

        $response = $this->withToken()->getJson("/api/history/{$history->history_id}");

        $response->assertStatus(200)
                 ->assertJsonFragment(['history_id' => $history->history_id]);
    }
}
