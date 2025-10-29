<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;


class AuthController extends Controller
{
    public function register(Request $request)
    {
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,user_email',
            'password' => 'required|min:6',
            'username' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $user = User::create([
            'user_email'    => $validated['email'],
            'user_password' => Hash::make($validated['password']),
            'user_username' => $validated['username'],
            'user_verified' => false,
            'user_img'      => null, // optional default
        ]);

        // Send verification email
        $token = Str::random(60);
        $verifyUrl = url("/api/verify-email?email={$user->user_email}&token={$token}");

        cache()->put('verify_' . $user->user_email, $token, 3600); // valid for 1 hour

        Mail::raw("Click to verify your email: $verifyUrl", function ($message) use ($user) {
            $message->to($user->user_email)
                    ->subject('Verify your email address');
        });

        return response()->json(['message' => 'User created, please verify email.']);
    }

    public function verifyEmail(Request $request)
    {
        $email = $request->query('email');
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
        // Validate input
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // Map request to your DB column names
        $credentials = [
            'user_email' => $request->input('email'),
            'password'   => $request->input('password') // JWTAuth expects 'password' key
        ];

        // Check if user exists
        $user = User::where('user_email', $credentials['user_email'])->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        if (!$user->user_verified) {
            return response()->json(['error' => 'Email not verified'], 401);
        }

        try {
            // Attempt to create a JWT token
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json(['error' => 'Invalid credentials'], 401);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'Could not create token'], 500);
        }

        return response()->json([
            'message' => 'Login successful',
            'token'   => $token,
            'token_type' => 'bearer',
            'expires_in' => (int) JWTAuth::factory()->getTTL() * 60
        ]);

    }

    public function me()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized. Please log in.'
            ], 401);
        }
        return response()->json($user);
    }

    public function updateProfile(Request $request)
    {
        $user = auth('api')->user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized. Please log in.'
            ], 401);
        }

        $request->validate([
            'username' => 'sometimes|string|max:255',
            'image' => 'sometimes|string|max:255',
        ]);

        if ($request->has('username')) {
            $user->user_username = $request->username;
        }

        if ($request->has('image')) {
            $path =  $request->input('user_img');
            $user->user_img = $path;
        }

        $user->save();

        return response()->json(['message' => 'Profile updated', 'user' => $user]);
    }

    public function deleteAccount()
    {
        $user = auth('api')->user();
        $user->delete();

        return response()->json(['message' => 'Account deleted']);
    }

    public function logout()
    {
        try {
            JWTAuth::parseToken()->invalidate(); // invalidate the current token
            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to logout, token invalid or expired'
            ], 500);
        }
    }


    public function refresh()
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());

            return response()->json([
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to refresh token'], 401);
        }
    }

}
