<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Run;
use App\Models\Flag;
use Tymon\JWTAuth\Facades\JWTAuth;

class RunApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user
        $this->user = User::factory()->create();

        // Generate JWT token
        $this->token = JWTAuth::fromUser($this->user);
    }

    // Helper to add Authorization header
    protected function withAuthToken($token = null)
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . ($token ?? $this->token),
        ]);
    }

    /** @test */
    public function user_can_create_run()
    {
        $data = [
            'run_title' => 'Test Run',
            'run_type' => 1,
            'run_description' => 'A test run',
        ];

        $response = $this->withAuthToken()->postJson('/api/runs', $data);

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

        $response = $this->withAuthToken()->putJson("/api/runs/{$run->run_id}", [
            'run_title' => 'Updated Run',
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['run_title' => 'Updated Run']);
    }

    /** @test */
    public function user_can_delete_run()
    {
        $run = Run::factory()->create(['user_id' => $this->user->user_id]);

        $response = $this->withAuthToken()->deleteJson("/api/runs/{$run->run_id}");

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

        $response = $this->withAuthToken()->putJson("/api/runs/{$run->run_id}/flags/bulk", $payload);

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

        $response = $this->withAuthToken()->deleteJson("/api/runs/{$run->run_id}/flags/bulk", [
            'flag_ids' => $flagIds,
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Flags deleted']);

        foreach ($flagIds as $id) {
            $this->assertDatabaseMissing('flags', ['flag_id' => $id]);
        }
    }
}
