<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use App\Notifications\EmailVerificationOtpNotification;
use App\Services\UserService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Register a new user.
     *
     * @param  AddUserRequest  $request
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->saveUser($request->validated());

            event(new Registered($user));

            // Generate a 6-digit OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Store the OTP in the database (hashed for security)
            $user->email_verification_otp = Hash::make($otp);
            $user->save();

            // Send the OTP notification
            $user->notify(new EmailVerificationOtpNotification($otp));

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully. Please verify your email with the OTP sent.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Login user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->validated();

            // Find user by email
            $user = User::where('email', $credentials['email'])->first();

            // Check if user exists and password is correct
            if (! $user || ! Hash::check($credentials['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ], 401);
            }

            // Check if email is verified
            // if (! $user->hasVerifiedEmail()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Please verify your email before logging in.',
            //         'email_verified' => false,
            //     ], 403);
            // }

            // Create access token using Laravel Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Verify email using OTP (public endpoint - no authentication required).
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'otp' => 'required|string|size:6',
            ]);

            // Find user by email
            $user = User::where('email', $request->email)->first();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 404);
            }

            // Check if already verified
            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email already verified.',
                ], 400);
            }

            // Check if OTP exists
            if (! $user->email_verification_otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'No OTP found. Please request a new one.',
                ], 400);
            }

            // Verify OTP
            if (! Hash::check($request->otp, $user->email_verification_otp)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP.',
                ], 400);
            }

            // Mark email as verified
            $user->email_verified_at = now();
            $user->email_verification_otp = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Email verified successfully! You can now login.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Verification failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Logout user (Revoke current access token).
     */
    public function logout(): JsonResponse
    {
        try {
            // Revoke the token that was used to authenticate the current request
            auth()->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
