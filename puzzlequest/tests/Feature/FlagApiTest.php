<?php

namespace Tests\Feature;

use Tests\ApiTestCase;
use App\Models\Run;
use App\Models\Flag;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FlagApiTest extends ApiTestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_bulk_create_flags()
    {
        $this->authenticate();
        $run = Run::factory()->create(['user_id' => $this->user->user_id]);

        $payload = [
            ['flag_number' => 1, 'flag_lat' => 12.34, 'flag_long' => 56.78],
            ['flag_number' => 2, 'flag_lat' => 23.45, 'flag_long' => 67.89],
        ];

        $response = $this->withToken()->postJson("/api/runs/{$run->run_id}/flags/bulk", $payload);

        $response->assertStatus(201)
                 ->assertJsonCount(2);

        $this->assertDatabaseCount('flags', 2);
    }

    /** @test */
    public function user_can_bulk_update_flags()
    {
        $this->authenticate();
        $run = Run::factory()->create(['user_id' => $this->user->user_id]);
        $flags = Flag::factory()->count(2)->create(['run_id' => $run->run_id]);

        $payload = $flags->map(function ($f) {
            return [
                'flag_id' => $f->flag_id,
                'flag_lat' => $f->flag_lat + 1,
                'flag_long' => $f->flag_long + 1
            ];
        })->toArray();

        $response = $this->withToken()->putJson("/api/runs/{$run->run_id}/flags/bulk", $payload);

        $response->assertStatus(200);
    }

    /** @test */
    public function user_can_bulk_delete_flags()
    {
        $this->authenticate();
        $run = Run::factory()->create(['user_id' => $this->user->user_id]);
        $flags = Flag::factory()->count(2)->create(['run_id' => $run->run_id]);

        $ids = $flags->pluck('flag_id')->toArray();

        $response = $this->withToken()->deleteJson("/api/runs/{$run->run_id}/flags/bulk", ['ids' => $ids]);

        $response->assertStatus(200);

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('flags', ['flag_id' => $id]);
        }
    }
}
