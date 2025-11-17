<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Validation\Rule;

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

        try {
            $registerResponse = Http::asForm()->post(url('/api/register'), $payload);

            if ($registerResponse->successful()) {
                $loginResponse = Http::asForm()->post(url('/api/login'), [
                    'email' => $payload['email'],
                    'password' => $payload['password'],
                ]);

                if ($loginResponse->successful()) {
                    $respBody = $loginResponse->json();
                    $token = $respBody['token'] ?? ($respBody['access_token'] ?? null);

                    if ($token) {
                        session(['api_token' => $token, 'jwt_token' => $token]);

                        try {
                            $user = JWTAuth::setToken($token)->toUser();
                            if ($user) {
                                Auth::login($user);
                                $request->session()->regenerate();
                            }
                        } catch (\Throwable $e) {
                            // ignore token parse failures for web guard
                        }
                    }

                    session()->forget('guest');
                    return redirect()->to($request->getSchemeAndHttpHost() . '/')->with('success', 'Account created');
                }

                // If login failed, clear guest and send to login
                session()->forget('guest');
                return redirect()->route('login')->with('success', 'Account created, please verify email.');
            }

            if ($registerResponse->status() === 422) {
                $errors = $registerResponse->json('errors') ?? ['error' => 'Validation failed'];
                return back()->withErrors($errors)->withInput();
            }

            $message = $registerResponse->json('message') ?? 'Upgrade failed';
            return back()->withErrors(['error' => $message])->withInput();
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Upgrade request failed: ' . $e->getMessage()])->withInput();
        }
    }

    public function endGuest(Request $request)
    {
        if ($request->session()->has('guest')) {
            $request->session()->forget('guest');
        }

        return redirect()->to($request->getSchemeAndHttpHost() . '/')->with('success', 'Guest session ended');
    }
    public function me(Request $request)
    {
        return view('auth.profile');
    }

    public function showEditProfile()
    {
        $dir = public_path('images/profiles');
        $files = File::exists($dir) ? collect(File::files($dir)) : collect();
        $profileImages = $files
            ->filter(function ($f) {
                $ext = strtolower($f->getExtension());
                return in_array($ext, ['png','jpg','jpeg','gif','webp']);
            })
            ->map(function ($f) { return $f->getFilename(); })
            ->values()
            ->all();

        return view('auth.edit-profile', [
            'profileImages' => $profileImages,
        ]);
    }

    public function updateProfile(Request $request)
    {
        // Build allowed image choices from public/profiles for validation
        $dir = public_path('images/profiles');
        $files = File::exists($dir) ? collect(File::files($dir)) : collect();
        $choices = $files
            ->filter(function ($f) {
                $ext = strtolower($f->getExtension());
                return in_array($ext, ['png','jpg','jpeg','gif','webp']);
            })
            ->map(function ($f) { return $f->getFilename(); })
            ->values()
            ->all();

        $request->validate([
            'username' => 'required|string|max:255',
            'user_img' => ['nullable','string', Rule::in($choices)],
        ]);

        // Call internal API update-profile endpoint
        try {
            $token = $request->session()->get('jwt_token');
            if (!$token) {
                return redirect()->route('login')->with('error', 'Please log in.');
            }

            $apiRequest = Request::create('/api/update-profile', 'PATCH', [
                'username' => $request->input('username'),
                'image' => $request->input('user_img'),
            ], [], [], [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token
            ]);

            $apiResponse = app()->handle($apiRequest);
            $status = $apiResponse->getStatusCode();

            if ($status >= 200 && $status < 300) {
                return redirect()->route('profile')->with('success', 'Profile updated successfully!');
            }

            $body = json_decode($apiResponse->getContent(), true) ?: [];
            $errors = $body['errors'] ?? ['error' => [$body['message'] ?? 'Failed to update profile']];
            return back()->withErrors($errors)->withInput();
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update profile: ' . $e->getMessage()])->withInput();
        }
    }

    public function showChangePassword()
    {
        return view('auth.change-password');
    }

    public function changePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Verify current password
        if (!Hash::check($request->input('current_password'), $user->user_password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.'])->withInput();
        }

        try {
            $user->user_password = Hash::make($request->input('password'));
            $user->save();

            return redirect()->route('profile')->with('success', 'Password changed successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to change password: ' . $e->getMessage()]);
        }
    }

    public function deleteAccount(Request $request)
    {
        // Call internal API delete endpoint
        try {
            $token = $request->session()->get('jwt_token');
            if (!$token) {
                return redirect()->route('login')->with('error', 'Please log in.');
            }

            $apiRequest = Request::create('/api/delete', 'DELETE', [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token
            ]);

            $apiResponse = app()->handle($apiRequest);
            $status = $apiResponse->getStatusCode();

            // Log out the user session
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($status >= 200 && $status < 300) {
                return redirect('/')->with('success', 'Your account has been deleted successfully.');
            }

            return redirect('/')->with('error', 'Failed to delete account.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete account: ' . $e->getMessage()]);
        }
    }

    /**
     * Show the forgot password form
     */
    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send password reset email
     */
    public function sendResetLink(Request $request)
    {
        // Call internal API forgot-password endpoint
        try {
            $apiRequest = HttpRequest::create('/api/forgot-password', 'POST', [
                'email' => $request->input('email'),
            ], [], [], ['HTTP_ACCEPT' => 'application/json']);

            $apiResponse = app()->handle($apiRequest);
            $status = $apiResponse->getStatusCode();
            $body = json_decode($apiResponse->getContent(), true) ?: [];

            if ($status >= 200 && $status < 300) {
                return back()->with('success', 'Password reset link has been sent to your email.');
            }

            // Handle API errors
            $errors = $body['errors'] ?? ['email' => [$body['message'] ?? 'Failed to send reset link']];
            return back()->withErrors($errors);
        } catch (\Exception $e) {
            return back()->withErrors(['email' => 'An error occurred. Please try again.']);
        }
    }

    /**
     * Show the reset password form
     */
    public function showResetPassword(Request $request)
    {
        return view('auth.reset-password', [
            'token' => $request->token,
            'email' => $request->email
        ]);
    }

    /**
     * Process password reset
     */
    public function resetPassword(Request $request)
    {
        // Call internal API reset-password endpoint
        try {
            $apiRequest = HttpRequest::create('/api/reset-password', 'POST', [
                'email' => $request->input('email'),
                'token' => $request->input('token'),
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
            ], [], [], ['HTTP_ACCEPT' => 'application/json']);

            $apiResponse = app()->handle($apiRequest);
            $status = $apiResponse->getStatusCode();
            $body = json_decode($apiResponse->getContent(), true) ?: [];

            if ($status >= 200 && $status < 300) {
                return redirect()->route('login')->with('success', 'Password reset successfully. You can now login with your new password.');
            }

            // Handle API errors
            $errors = $body['errors'] ?? ['token' => [$body['message'] ?? 'Failed to reset password']];
            return back()->withErrors($errors);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'An error occurred. Please try again.']);
        }
    }
}
