<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ApiClient
{
    protected string $baseUrl;

    public function __construct()
    {
        // You can also put this in .env (e.g., API_BASE_URL)
        $this->baseUrl = rtrim(config('app.url'), '/') . '/api';
    }

    /**
     * Send POST request to an internal API endpoint.
     */
    protected function post(string $endpoint, array $data = [])
    {
        $response = Http::post("{$this->baseUrl}/{$endpoint}", $data);

        if ($response->failed()) {
            return [
                'success' => false,
                'status' => $response->status(),
                'errors' => $response->json('errors') ?? ['message' => 'Unknown API error'],
            ];
        }

        return [
            'success' => true,
            'data' => $response->json(),
        ];
    }

    /**
     * Call /api/register
     */
    public function registerUser(array $data)
    {
        return $this->post('register', $data);
    }

    /**
     * Call /api/guest/init
     */
    public function initGuest(array $data)
    {
        return $this->post('guest/init', $data);
    }
}
