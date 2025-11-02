<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class WebGuestUpgradeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_creation_and_navbar_shows_upgrade()
    {
        $resp = $this->post('/guest', ['guest_name' => 'Visitor']);
        $resp->assertRedirect('/');

        $this->get('/')->assertSee('Upgrade');
        $this->assertTrue(session()->has('guest'));
    }

    public function test_end_guest_clears_session_and_nav_shows_login_register()
    {
        // create a guest in session
        $this->post('/guest', ['guest_name' => 'ByeGuest']);
        $this->assertTrue(session()->has('guest'));

        // end guest
        $resp = $this->post('/guest/end');
        $resp->assertRedirect('/');

        $this->assertFalse(session()->has('guest'));

    // homepage should show Log in/Register/Guest links and not show Upgrade
    $this->get('/')->assertSee('Log in')->assertSee('Register')->assertSee('Guest')->assertDontSee('Upgrade');
    }

    public function test_upgrade_calls_api_and_logs_in_user()
    {
        // Ensure JWT secret present for token generation in faked responses
        config(['jwt.secret' => env('JWT_SECRET', 'testingsecret')]);
        // Fake HTTP for API register and login
        Http::fake(function ($request) {
            $url = $request->url();

            // parse form body (asForm sends application/x-www-form-urlencoded)
            $body = (string) $request->body();
            parse_str($body, $data);

            if (str_ends_with($url, '/api/register')) {
                // create a local DB user to simulate registration
                User::create([
                    'user_email' => $data['email'] ?? 'unknown@example.com',
                    'user_password' => Hash::make($data['password'] ?? 'pw'),
                    'user_name' => $data['username'] ?? 'guest',
                    'user_verified' => true,
                ]);

                return Http::response(['message' => 'User created'], 200);
            }

            if (str_ends_with($url, '/api/login')) {
                $email = $data['email'] ?? null;
                $password = $data['password'] ?? null;
                $user = User::where('user_email', $email)->first();
                if ($user && Hash::check($password, $user->user_password)) {
                    $token = JWTAuth::fromUser($user);
                    return Http::response(['message' => 'Login successful', 'token' => $token], 200);
                }
                return Http::response(['error' => 'Invalid credentials'], 401);
            }

            return Http::response([], 404);
        });

        // Start as guest
        $this->post('/guest', ['guest_name' => 'UpgradeMe']);

        // Perform upgrade - provide username/email/password
        $resp = $this->post('/upgrade', [
            'username' => 'upgrader',
            'email' => 'upgrader@example.com',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ]);

        // After upgrade should redirect to home and guest removed
        $resp->assertRedirect('/');
        $this->assertFalse(session()->has('guest'));

        // Check that api_token saved in session
        $this->assertTrue(session()->has('api_token'));

        // And local auth should be logged in
        $this->assertAuthenticated();
    }
}
