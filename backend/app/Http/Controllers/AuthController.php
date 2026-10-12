<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Services\AuditService;
use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private AuditService $audit
    ) {}


    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => [
                'required',
                'email',
            ],

            'password' => [
                'required',
                'string',
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | Find User
        |--------------------------------------------------------------------------
        */

        $user = User::where(
            'email',
            $credentials['email']
        )->first();


        /*
        |--------------------------------------------------------------------------
        | Check Credentials
        |--------------------------------------------------------------------------
        */

        if (
            ! $user ||
            ! Hash::check(
                $credentials['password'],
                $user->password
            )
        ) {

            throw ValidationException::withMessages([
                'email' => [
                    'Invalid credentials.'
                ],
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Check User Status
        |--------------------------------------------------------------------------
        */

        if (! $user->isActive()) {

            return ApiResponse::error(
                'Account is not active.',
                [],
                403
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Login User
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | Session middleware must be active.
        |
        */

        Auth::guard('web')->login(
            $user,
            true
        );


        /*
        |--------------------------------------------------------------------------
        | Regenerate Session
        |--------------------------------------------------------------------------
        */

        $request->session()->regenerate();


        /*
        |--------------------------------------------------------------------------
        | Update Last Login
        |--------------------------------------------------------------------------
        */

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();


        /*
        |--------------------------------------------------------------------------
        | Audit Log
        |--------------------------------------------------------------------------
        */

        $this->audit->log(
            'LOGIN',
            'AUTH',
            $user->id,
            null,
            null,
            $request
        );


        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            [
                'user' => [
                    'id' => $user->id,

                    'name' => $user->name,

                    'email' => $user->email,

                    'role' => $user->role->value,

                    'status' => $user->status->value,

                    'client_id' => $user->client_id,
                ],
            ],
            'Logged in'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */

    public function logout(Request $request)
    {
        $user = $request->user();


        /*
        |--------------------------------------------------------------------------
        | Audit Logout
        |--------------------------------------------------------------------------
        */

        if ($user) {

            $this->audit->log(
                'LOGOUT',
                'AUTH',
                $user->id,
                null,
                null,
                $request
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Logout
        |--------------------------------------------------------------------------
        */

        Auth::guard('web')->logout();


        /*
        |--------------------------------------------------------------------------
        | Invalidate Session
        |--------------------------------------------------------------------------
        */

        $request->session()->invalidate();


        /*
        |--------------------------------------------------------------------------
        | Regenerate CSRF Token
        |--------------------------------------------------------------------------
        */

        $request->session()->regenerateToken();


        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            null,
            'Logged out'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CURRENT USER
    |--------------------------------------------------------------------------
    */

    public function me(Request $request)
    {
        $user = $request->user();


        /*
        |--------------------------------------------------------------------------
        | Not Authenticated
        |--------------------------------------------------------------------------
        */

        if (! $user) {

            return ApiResponse::error(
                'Unauthenticated',
                [],
                401
            );
        }


        /*
        |--------------------------------------------------------------------------
        | User Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            [
                'id' => $user->id,

                'name' => $user->name,

                'email' => $user->email,

                'role' => $user->role->value,

                'status' => $user->status->value,

                'client_id' => $user->client_id,
            ],
            'OK'
        );
    }
}