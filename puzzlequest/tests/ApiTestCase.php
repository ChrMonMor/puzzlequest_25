<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

abstract class ApiTestCase extends BaseTestCase
{
    use CreatesApplication;

    protected $user;
    protected $token;

    /** Authenticate a user and get a JWT token */
    protected function authenticate()
    {
        $this->user = User::factory()->create([
            'user_verified' => true,
        ]);
        $this->token = JWTAuth::fromUser($this->user);

        return $this->token;
    }

    /** Add Authorization header to request */
    public function withToken(string $token, string $type = 'Bearer')
    {
        return $this->withHeaders([
            'Authorization' => $type . ' ' . $token,
        ]);
    }
}
