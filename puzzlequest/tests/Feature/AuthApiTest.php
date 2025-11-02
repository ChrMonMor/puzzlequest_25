<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user()
    {
        $payload = [
            'username' => 'tester',
            'email' => 'tester@example.com',
            'password' => 'secret123',
        ];

        $resp = $this->postJson('/api/register', $payload);

        $resp->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'user_email' => 'tester@example.com',
            'user_name' => 'tester',
        ]);
    }

    public function test_login_returns_token_for_verified_user()
    {
        // create a verified user in the DB
        $user = User::create([
            'user_email' => 'login@example.com',
            'user_password' => Hash::make('password123'),
            'user_name' => 'loginuser',
            'user_verified' => true,
        ]);

        // Ensure JWT secret is set during tests
        config(['jwt.secret' => env('JWT_SECRET', 'testingsecret')]);

        $resp = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $resp->assertStatus(200)->assertJsonStructure(['message', 'token']);

        $data = $resp->json();
        $this->assertArrayHasKey('token', $data);
    }

    public function test_me_endpoint_returns_user_with_bearer_token()
    {
        $user = User::create([
            'user_email' => 'me@example.com',
            'user_password' => Hash::make('mepass123'),
            'user_name' => 'meuser',
            'user_verified' => true,
        ]);

        // Ensure JWT secret
        config(['jwt.secret' => env('JWT_SECRET', 'testingsecret')]);

        // Generate token using JWTAuth facade
        $token = JWTAuth::fromUser($user);

        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
                     ->postJson('/api/user');

        $resp->assertStatus(200);
        $resp->assertJsonFragment(['user_email' => 'me@example.com']);
    }
}
