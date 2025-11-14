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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;



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

        $rawToken = Str::random(60);
        $hashedToken = Hash::make($rawToken);

        $cacheKey = 'verify_' . $validated['email'];
        $cachePayload = [
            'hashed_token' => $hashedToken,
            'username' => $validated['username'],
            'password_hash' => Hash::make($validated['password']),
            'email' => $validated['email'],
        ];

        // Cache the registration data for 1 hour
        cache()->put($cacheKey, $cachePayload, 3600);

        $verifyUrl = url('/verify-email') . '?email=' . urlencode($validated['email']) . '&token=' . urlencode($rawToken);

        Mail::raw("Click to verify your email: $verifyUrl", function ($message) use ($validated) {
            $message->to($validated['email'])
                    ->subject('Verify your email address');
        });

        return response()->json(['message' => 'Registration received, please check your email to verify.']);
    }

    public function verifyEmail(Request $request)
    {
        $email = $request->query('email');
        $token = $request->query('token');

        $cacheKey = 'verify_' . $email;
        $cached = cache()->get($cacheKey);

        if (!$cached || empty($cached['hashed_token'])) {
            return response()->json(['message' => 'Invalid or expired token'], 400);
        }

        // Validate the raw token against the hashed token stored in cache
        if (!Hash::check($token, $cached['hashed_token'])) {
            return response()->json(['message' => 'Invalid or expired token'], 400);
        }

        // If the user already exists, just mark verified. Otherwise create them
        $user = User::where('user_email', $email)->first();
        if (!$user) {
            $user = User::create([
                'user_email' => $cached['email'],
                'user_password' => $cached['password_hash'],
                'user_name' => $cached['username'],
                'user_verified' => true,
                'user_img' => asset('images/androgynousDefault.png'),
                'user_email_verified_at' => now(),
            ]);
        } else {
            $user->user_verified = true;
            $user->user_email_verified_at = now();
            $user->save();
        }

        cache()->forget($cacheKey);

        return response()->json(['message' => 'Email verified and account created successfully']);
    }

    /**
     * Log a user in and return a JWT token
     *
     * @bodyParam email string required User email. Example: "user@example.com"
     * @bodyParam password string required User password.
     * @response 200 {"token":"jwt-token","user":{"user_id":"uuid","user_name":"bob"}}
     */
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
            'password'   => $request->input('password'),
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
        $user = auth('api')->user();
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
            $user->user_name = $request->username;
        }

        if ($request->has('image')) {
            $path =  $request->input('image');
            $user->user_img = $path;
        }

        $user->save();

        return response()->json(['message' => 'Profile updated', 'user' => $user]);
    }

    public function deleteAccount()
    {
        $user = auth('api')->user();
        if ($user) {
            $user->delete();
        }

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

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,user_email',
        ]);

        $email = $request->email;

        $rawToken = Str::random(64);

        $hashedToken = Hash::make($rawToken);

        Cache::put('password_resets_' . $email, $hashedToken, now()->addHour());

        $resetUrl = url("/api/reset-password?token=$rawToken&email=$email");

        Mail::raw("Click the link to reset your password: $resetUrl", function ($message) use ($email) {
            $message->to($email)->subject('Reset your password');
        });

        return response()->json(['message' => 'Password reset email sent.']);
    }
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,user_email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $email = $request->email;
        $token = $request->token;

        $hashedToken = Cache::get('password_resets_' . $email);

        if (!$hashedToken || !Hash::check($token, $hashedToken)) {
            return response()->json(['message' => 'Invalid or expired token'], 400);
        }

        DB::table('users')
            ->where('user_email', $email)
            ->update([
                'user_password' => Hash::make($request->password),
            ]);

        Cache::forget('password_resets_' . $email);

        return response()->json(['message' => 'Password reset successfully.']);
    }

    public function initGuest(Request $request)
    {
        $request->validate([
            'guest_name' => 'nullable|string|max:50',
        ]);

        $guest_uuid = (string) Str::uuid();

        $name = $request->input('guest_name');
        if (empty($name)) {
            $name = 'Guest ' . substr($guest_uuid, 0, 8);
        }

        $guestData = [
            'guest_uuid' => $guest_uuid,
            'guest_name'=> $name,
            'created_at' => now(),
        ];

        // Cache guest for 1 day (adjust duration as needed)
        Cache::put("guest:{$guest_uuid}", $guestData, now()->addDay());

        return response()->json([
            'guest_uuid' => $guest_uuid,
            'guest_name'=> $name,
            'expires_at' => now()->addDay()->toDateTimeString(),
        ]);
    }
    public function endGuest(Request $request)
    {
        $token = $request->bearerToken() ?? $request->query('guest_token');

        if (!$token) {
            return response()->json(['error' => 'Guest token missing'], 401);
        }

        if (!Cache::has("guest:{$token}")) {
            return response()->json(['error' => 'Invalid or expired guest token'], 404);
        }

        Cache::forget("guest:{$token}");

        return response()->json(['message' => 'Guest session ended successfully']);
    }

    public function upgradeGuest(Request $request)
    {
        $token = $request->bearerToken() ?? $request->input('guest_token');

        if (!$token) {
            return response()->json(['error' => 'Guest token missing'], 401);
        }

        $guest = Cache::get("guest:{$token}");

        if (!$guest) {
            return response()->json(['error' => 'Invalid or expired guest token'], 401);
        }

        // Validate registration input
        $validated = $request->validate([
            'username' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Create the user
        $user = User::create([
            'user_name' => $validated['username'],
            'user_email' => $validated['email'],
            'user_password' => Hash::make($validated['password']),
        ]);

        // Remove guest from cache
        Cache::forget("guest:{$token}");

        // Generate JWT token for the new user
        try {
            $jwt = JWTAuth::fromUser($user);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate token'], 500);
        }

        return response()->json([
            'message' => 'Guest upgraded successfully',
            'user' => $user,
            'token' => $jwt,
        ]);
    }
    
    public function getGuestInfo(Request $request)
    {
        $token = $request->bearerToken() ?? $request->query('guest_token');

        if (!$token || !Cache::has("guest:{$token}")) {
            return response()->json(['error' => 'Invalid or missing guest token'], 404);
        }

        $guest = Cache::get("guest:{$token}");

        return response()->json(['guest' => $guest]);
    }
}
