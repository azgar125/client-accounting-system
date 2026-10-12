<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Login
     */
    public function login(Request $request)
    {
        try {

            // 1. Validate request
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            // 2. Find user
            $user = User::where('email', $credentials['email'])->first();

            // 3. Check email/password
            if (
                ! $user ||
                ! Hash::check($credentials['password'], $user->password)
            ) {
                throw ValidationException::withMessages([
                    'email' => ['Invalid credentials.'],
                ]);
            }

            // 4. Check active status
            if (! $user->isActive()) {
                return ApiResponse::error(
                    'Account is not active.',
                    [],
                    403
                );
            }

            // 5. Login using web session
            Auth::guard('web')->login($user, true);

            // 6. Regenerate session
            $request->session()->regenerate();

            // 7. Update last login
            $user->forceFill([
                'last_login_at' => now(),
            ])->save();

            // 8. Log successful login
            Log::info('User logged in successfully', [
                'user_id' => $user->id,
                'email'   => $user->email,
            ]);

            // 9. Return response
            return ApiResponse::success([
                'user' => [
                    'id'        => $user->id,
                    'name'      => $user->name,
                    'email'     => $user->email,
                    'role'      => $this->getEnumValue($user->role),
                    'status'    => $this->getEnumValue($user->status),
                    'client_id' => $user->client_id,
                ],
            ], 'Logged in');

        } catch (ValidationException $e) {

            // Validation errors
            throw $e;

        } catch (\Throwable $e) {

            // Log complete error
            Log::error('Login error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'email'   => $request->input('email'),
            ]);

            // TEMPORARY DEBUG RESPONSE
            return response()->json([
                'success' => false,
                'debug'   => true,
                'error'   => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }


    /**
     * Logout
     */
    public function logout(Request $request)
    {
        try {

            // Get authenticated user
            $user = $request->user();

            // Logout
            Auth::guard('web')->logout();

            // Invalidate session
            $request->session()->invalidate();

            // Regenerate CSRF token
            $request->session()->regenerateToken();

            Log::info('User logged out', [
                'user_id' => $user?->id,
            ]);

            return ApiResponse::success(
                null,
                'Logged out'
            );

        } catch (\Throwable $e) {

            Log::error('Logout error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'debug'   => true,
                'error'   => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }


    /**
     * Get currently authenticated user
     */
    public function me(Request $request)
    {
        try {

            $user = $request->user();

            if (! $user) {
                return ApiResponse::error(
                    'Unauthenticated',
                    [],
                    401
                );
            }

            return ApiResponse::success([
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'role'      => $this->getEnumValue($user->role),
                'status'    => $this->getEnumValue($user->status),
                'client_id' => $user->client_id,
            ], 'OK');

        } catch (\Throwable $e) {

            Log::error('Me endpoint error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'debug'   => true,
                'error'   => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }


    /**
     * Safely get Enum value.
     *
     * Works with PHP Enum and normal string values.
     */
    private function getEnumValue($value)
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        return $value;
    }
}