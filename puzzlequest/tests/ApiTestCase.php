<?php

namespace Tests;

use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class ApiTestCase extends BaseTestCase
{
    use CreatesApplication;

    protected ?User $user = null;
    protected ?string $token = null;

    /**
     * Authenticate a user with JWT and store the token.
     */
    public function authenticate(?User $user = null): void
    {
        $this->user = $user ?: User::factory()->create();
        $this->token = JWTAuth::fromUser($this->user);
    }

    /**
     * Attach the JWT token to future requests.
     */
    public function withToken(?string $token = null, string $type = 'Bearer')
    {
        $token = $token ?? $this->token;

        if (!$token) {
            throw new \RuntimeException('No token set. Call $this->authenticate() before using withToken().');
        }

        return $this->withHeaders([
            'Authorization' => "{$type} {$token}",
            'Accept' => 'application/json',
        ]);
    }
}
