<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

class AuthVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_validation_errors()
    {
        // missing fields
        $resp = $this->postJson('/api/register', []);
        $resp->assertStatus(422);
    }

    public function test_register_duplicate_email_fails()
    {
        User::create([
            'user_email' => 'dup@example.com',
            'user_password' => Hash::make('pw123456'),
            'user_name' => 'dup',
            'user_verified' => false,
        ]);

        $payload = [
            'username' => 'dup',
            'email' => 'dup@example.com',
            'password' => 'password',
        ];

        $resp = $this->postJson('/api/register', $payload);
        $resp->assertStatus(422);
    }

    public function test_verify_email_with_valid_token_marks_user_verified()
    {
        // Register via endpoint (which stores a hashed token in cache)
        $email = 'verify@example.com';

        $resp = $this->postJson('/api/register', [
            'username' => 'verifyuser',
            'email' => $email,
            'password' => 'password',
        ]);

        $resp->assertStatus(200);

        $cached = Cache::get('verify_' . $email);
        $this->assertNotEmpty($cached, 'Expected verify token in cache after registration');

        // Call verify endpoint with the same token that was cached
        $verifyResp = $this->getJson('/api/verify-email?email=' . urlencode($email) . '&token=' . urlencode($cached));
        $verifyResp->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'user_email' => $email,
            'user_verified' => 1,
        ]);
    }

    public function test_verify_email_with_invalid_token_fails()
    {
        $email = 'badtoken@example.com';

        // Create user and manually set a token in cache
        $user = User::create([
            'user_email' => $email,
            'user_password' => Hash::make('pw'),
            'user_name' => 'bad',
            'user_verified' => false,
        ]);

        Cache::put('verify_' . $email, 'sometoken', 3600);

        $resp = $this->getJson('/api/verify-email?email=' . urlencode($email) . '&token=wrongtoken');
        $resp->assertStatus(400);
    }
}
