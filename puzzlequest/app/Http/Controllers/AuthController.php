<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'user_email' => 'required|email|unique:users',
            'user_password' => 'required|min:6',
            'user_username' => 'required|string|max:255'
        ]);

        $user = User::create([
            'user_email' => $validated['user_email'],
            'user_password' => Hash::make($validated['user_password']),
            'user_username' => $validated['user_username'],
            'user_verified' => false
        ]);

        // Send verification email
        $token = Str::random(60);
        $verifyUrl = url("/api/verify-email?email={$user->user_email}&token={$token}");

        cache()->put('verify_' . $user->user_email, $token, 3600); // 1h token lifetime

        Mail::raw("Click to verify your email: $verifyUrl", function($message) use ($user) {
            $message->to($user->user_email)
                    ->subject('Verify your email address');
        });

        return response()->json(['message' => 'User created, please verify email.']);
    }

    public function verifyEmail(Request $request)
    {
        $email = $request->query('user_email');
        $token = $request->query('token');

        $cachedToken = cache()->get('verify_' . $email);

        if (!$cachedToken || $cachedToken !== $token) {
            return response()->json(['message' => 'Invalid or expired token'], 400);
        }

        $user = User::where('user_email', $email)->firstOrFail();
        $user->user_verified = true;
        $user->user_email_verified_at = now();
        $user->save();

        cache()->forget('verify_' . $email);

        return response()->json(['message' => 'Email verified successfully']);
    }

    public function login(Request $request)
{
    $credentials = $request->only('email', 'password');
    $user = User::where('email', $credentials['email'])->first();

    if (!$user || !$user->verified) {
        return response()->json(['error' => 'Email not verified or invalid credentials'], 401);
    }

    try {
        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }
    } catch (JWTException $e) {
        return response()->json(['error' => 'Could not create token'], 500);
    }

    return response()->json(['token' => $token]);
}

    public function me()
    {
        return response()->json(auth()->user());
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'username' => 'sometimes|string|max:255',
            'user_image' => 'sometimes|image|max:2048',
        ]);

        if ($request->has('username')) {
            $user->username = $request->username;
        }

        if ($request->hasFile('user_image')) {
            $path = $request->file('user_image')->store('user_images', 'public');
            $user->user_image = $path;
        }

        $user->save();

        return response()->json(['message' => 'Profile updated', 'user' => $user]);
    }

    public function deleteAccount()
    {
        $user = auth()->user();
        $user->delete();

        return response()->json(['message' => 'Account deleted']);
    }
}
