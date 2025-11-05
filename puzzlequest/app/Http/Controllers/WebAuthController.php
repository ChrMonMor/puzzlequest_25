<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class WebAuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showGuest()
    {
        return view('auth.guest');
    }

    public function login(Request $request)
    {
        // 🔒 Validate the request
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $credentials = [
            'user_email' => $request->input('email'),
            'password'   => $request->input('password'),
        ];

        // 🧠 Try to authenticate using JWTAuth
        if ($token = Auth::attempt($credentials)) {
            // Store JWT in session (optional)
            session(['jwt_token' => $token]);

            $user = Auth::user();
            $request->session()->regenerate();

            return redirect('/')->with('success', 'Logged in successfully');
        }

        // 🚨 If JWT attempt failed, try fallback API (if you have external API)
        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->post(config('services.api.base_url') . '/login', [
                    'email' => $request->input('email'),
                    'password' => $request->input('password'),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $token = $data['token'] ?? $data['access_token'] ?? null;

                if ($token) {
                    session(['jwt_token' => $token]);
                }

                // Optionally fetch user info from API
                $me = Http::acceptJson()
                    ->withToken($token)
                    ->get(config('services.api.base_url') . '/user')
                    ->json();

                if (!empty($me['user_email'])) {
                    $user = User::firstOrCreate(
                        ['user_email' => $me['user_email']],
                        [
                            'name' => $me['name'] ?? '',
                            'password' => Hash::make(str()->random(16)),
                        ]
                    );

                    Auth::login($user);
                    $request->session()->regenerate();

                    return redirect('/')->with('success', 'Logged in via API');
                }

                return redirect('/')->with('success', 'Logged in (JWT stored)');
            }

            if ($response->status() === 401) {
                return back()->withErrors(['error' => 'Invalid credentials']);
            }

            return back()->withErrors(['error' => 'Login failed: ' . $response->status()]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Login request failed: ' . $e->getMessage()]);
        }
    }
    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        // 🧾 Validate the request
        $request->validate([
            'username' => 'required|string|max:255',
            'email' => 'required|email|unique:users,user_email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // 🧠 Create the user directly (no internal /api/register call)
        $user = User::create([
            'user_name'  => $request->username,
            'user_email' => $request->email,
            'user_password'   => Hash::make($request->password),
        ]);

        // 🔐 Optionally issue a JWT right after registration
        $token = JWTAuth::fromUser($user);

        // Store token in session (optional if you use web guards)
        session(['jwt_token' => $token]);

        // 🔁 Automatically log the user in (optional)
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/')
            ->with('success', 'Account created successfully! You are now logged in.');
    }

    public function logout(Request $request)
    {
        // Call API logout if token exists
        $token = session('jwt_token');
        if ($token) {
            try {
                // call internal logout API
                $symReq = Request::create('/api/logout', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'HTTP_ACCEPT' => 'application/json']);
                app()->handle($symReq);
            } catch (\Exception $e) {
                // ignore API logout failure
            }
            session()->forget('jwt_token');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/')->with('success', 'Logged out');
    }

    public function createGuest(Request $request)
    {
        // Allow optional guest display name and save guest info to session (no DB record)
        $request->validate([
            'guest_name' => 'nullable|string|max:50',
        ]);

        $uuid = (string) Str::uuid();
        $name = $request->input('guest_name');
        if (empty($name)) {
            $name = 'Guest ' . substr($uuid, 0, 8);
        }

        $guest = [
            'temp_id' => $uuid,
            'name' => $name,
            'created_at' => now()->toDateTimeString(),
        ];

        $request->session()->put('guest', $guest);

        return redirect('/')->with('success', 'Continuing as guest');
    }

    public function showUpgrade()
    {
        // Only allow upgrade if a session guest exists
        $guest = session('guest');
        if (!$guest) {
            return redirect('/login')->with('info', 'No guest session found');
        }

        return view('auth.upgrade', ['guest' => $guest]);
    }

    public function upgrade(Request $request)
    {
        $guest = session('guest');
        if (!$guest) {
            return redirect('/login')->with('info', 'No guest session found');
        }

        // Delegate to API register endpoint to create the account
        $request->validate([
            'username' => 'required|string|max:50',
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $payload = [
            'username' => $request->input('username', $guest['name'] ?? 'Guest'),
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ];

    // Call internal API register route
        $symRequest = Request::create('/api/register', 'POST', $payload, [], [], ['HTTP_ACCEPT' => 'application/json']);
        $symResponse = app()->handle($symRequest);
        $status = $symResponse->getStatusCode();
        $body = json_decode($symResponse->getContent(), true) ?: [];

        if ($status >= 200 && $status < 300) {
            // Find the created user and create a token directly (upgrade flow should log the
            // user in immediately). This avoids requiring email verification for the
            // upgrade-to-user flow (tests and UX expect immediate login).
            $user = User::where('user_email', $request->input('email'))->first();
            if ($user) {
                try {
                    $token = JWTAuth::fromUser($user);
                    if ($token) {
                        session(['jwt_token' => $token]);
                    }
                    Auth::login($user);
                } catch (\Exception $e) {
                    // If token creation fails, fall back to redirect to login
                    session()->forget('guest');
                    return redirect()->route('login')->with('success', $body['message'] ?? 'Account created, please verify email.');
                }

                session()->forget('guest');
                return redirect('/')->with('success', $body['message'] ?? 'Account created');
            }

            // If no local user found after creation, fall back to the login route
            session()->forget('guest');
            return redirect()->route('login')->with('success', $body['message'] ?? 'Account created, please verify email.');
        }

        if ($status === 422) {
            return back()->withErrors($body['errors'] ?? ['error' => 'Validation failed'])->withInput();
        }

        return back()->withErrors(['error' => $body['message'] ?? 'Upgrade failed'])->withInput();
    }

    public function endGuest(Request $request)
    {
        if ($request->session()->has('guest')) {
            $request->session()->forget('guest');
        }

        return redirect('/')->with('success', 'Guest session ended');
    }
}
