<?php

namespace Tests\Feature;

use Tests\ApiTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Run;
use App\Models\Flag;
use App\Models\RunType;

class RunApiTest extends ApiTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Authenticate user via ApiTestCase helper
        $this->authenticate();

        // Ensure there is at least one run type for FK constraints
        if (!RunType::first()) {
            RunType::factory()->create(['run_type_name' => 'Default']);
        }
    }

    /** @test */
    public function user_can_create_run()
    {
        $data = [
            'run_title' => 'Test Run',
            'run_type' => 1,
            'run_description' => 'A test run',
        ];

        $response = $this->withToken()->postJson('/api/runs', $data);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'run' => [
                         'run_id',
                         'run_title',
                         'run_type',
                         'run_description',
                     ],
                 ]);

        $this->assertDatabaseHas('runs', ['run_title' => 'Test Run']);
    }

    /** @test */
    public function user_can_update_run()
    {
        $run = Run::factory()->create(['user_id' => $this->user->user_id]);

        $response = $this->withToken()->putJson("/api/runs/{$run->run_id}", [
            'run_title' => 'Updated Run',
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['run_title' => 'Updated Run']);
    }

    /** @test */
    public function user_can_delete_run()
    {
        $run = Run::factory()->create(['user_id' => $this->user->user_id]);

        $response = $this->withToken()->deleteJson("/api/runs/{$run->run_id}");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Run deleted']);

        $this->assertDatabaseMissing('runs', ['run_id' => $run->run_id]);
    }

    /** @test */
    public function user_can_bulk_update_flags()
    {
        $run = Run::factory()->create(['user_id' => $this->user->user_id]);
        $flags = Flag::factory()->count(3)->create(['run_id' => $run->run_id]);

        $payload = $flags->map(fn($f) => [
            'flag_id' => $f->flag_id,
            'flag_number' => $f->flag_number + 10,
        ])->toArray();

        $response = $this->withToken()->putJson("/api/runs/{$run->run_id}/flags/bulk", $payload);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Flags updated']);

        foreach ($payload as $f) {
            $this->assertDatabaseHas('flags', [
                'flag_id' => $f['flag_id'],
                'flag_number' => $f['flag_number'],
            ]);
        }
    }

    /** @test */
    public function user_can_bulk_delete_flags()
    {
        $run = Run::factory()->create(['user_id' => $this->user->user_id]);
        $flags = Flag::factory()->count(2)->create(['run_id' => $run->run_id]);

        $flagIds = $flags->pluck('flag_id')->toArray();

        $response = $this->withToken()->deleteJson("/api/runs/{$run->run_id}/flags/bulk", [
            'ids' => $flagIds,
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Flags deleted']);

        foreach ($flagIds as $id) {
            $this->assertDatabaseMissing('flags', ['flag_id' => $id]);
        }
    }
}
