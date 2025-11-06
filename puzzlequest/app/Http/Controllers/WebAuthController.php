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
use Illuminate\Http\Request as HttpRequest;

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
        // Validate input
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // Call internal API login endpoint
        try {
            $sym = HttpRequest::create('/api/login', 'POST', [
                'email' => $request->input('email'),
                'password' => $request->input('password'),
            ], [], [], ['HTTP_ACCEPT' => 'application/json']);

            $symResponse = app()->handle($sym);
            $status = $symResponse->getStatusCode();
            $body = json_decode($symResponse->getContent(), true) ?: [];

            if ($status >= 200 && $status < 300) {
                $token = $body['token'] ?? $body['access_token'] ?? $body['token_type'] ?? ($body['access_token'] ?? null);
                // prefer 'token' or 'access_token'
                $token = $body['token'] ?? ($body['access_token'] ?? null);
                if ($token) {
                    session(['jwt_token' => $token]);
                    // try to resolve user from token and log into web guard
                    try {
                        $user = JWTAuth::setToken($token)->toUser();
                        if ($user) {
                            Auth::login($user);
                            $request->session()->regenerate();
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }

                return redirect()->to($request->getSchemeAndHttpHost() . '/')->with('success', $body['message'] ?? 'Logged in');
            }

            if ($status === 401) {
                return back()->withErrors(['error' => $body['error'] ?? 'Invalid credentials']);
            }

            return back()->withErrors(['error' => $body['message'] ?? 'Login failed']);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Login request failed: ' . $e->getMessage()]);
        }
    }
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Web-friendly verify endpoint: accepts email+token, calls API verify endpoint,
     * and renders a friendly view on success/failure.
     */
    public function verifyEmail(Request $request)
    {
        $email = $request->query('email');
        $token = $request->query('token');

        $sym = HttpRequest::create('/api/verify-email', 'GET', ['email' => $email, 'token' => $token], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $symResponse = app()->handle($sym);
        $status = $symResponse->getStatusCode();
        $body = json_decode($symResponse->getContent(), true) ?: [];

        if ($status >= 200 && $status < 300) {
            return view('auth.verified', ['message' => $body['message'] ?? 'Email verified successfully']);
        }

        return view('auth.verify_failed', ['message' => $body['message'] ?? 'Verification failed']);
    }

    public function register(Request $request)
    {
        // Validate input
        $request->validate([
            'username' => 'required|string|max:255',
            'email' => 'required|email|unique:users,user_email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Call internal API register endpoint (AuthController handles actual creation after email verification)
        try {
            $sym = HttpRequest::create('/api/register', 'POST', [
                'username' => $request->input('username'),
                'email' => $request->input('email'),
                'password' => $request->input('password'),
            ], [], [], ['HTTP_ACCEPT' => 'application/json']);

            $symResponse = app()->handle($sym);
            $status = $symResponse->getStatusCode();
            $body = json_decode($symResponse->getContent(), true) ?: [];

            if ($status >= 200 && $status < 300) {
                // Registration accepted — user must verify email
                return redirect()->to($request->getSchemeAndHttpHost() . '/')->with('success', $body['message'] ?? 'Registration received; please check your email to verify.');
            }

            if ($status === 422) {
                return back()->withErrors($body['errors'] ?? ['error' => 'Validation failed'])->withInput();
            }

            return back()->withErrors(['error' => $body['message'] ?? 'Registration failed'])->withInput();
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Registration request failed: ' . $e->getMessage()])->withInput();
        }
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
        return redirect()->to($request->getSchemeAndHttpHost() . '/')->with('success', 'Logged out');
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

        return redirect()->to($request->getSchemeAndHttpHost() . '/')->with('success', 'Continuing as guest');
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
                return redirect()->to($request->getSchemeAndHttpHost() . '/')->with('success', $body['message'] ?? 'Account created');
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

        return redirect()->to($request->getSchemeAndHttpHost() . '/')->with('success', 'Guest session ended');
    }
}
