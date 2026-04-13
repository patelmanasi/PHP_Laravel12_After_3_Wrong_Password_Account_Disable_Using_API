<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Account;
use App\Models\LoginAttempt;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * REGISTER API
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:accounts',
            'password' => [
                'required',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*?&]/'
            ]
        ]);

        $account = Account::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Account registered successfully',
            'data' => $account
        ]);
    }

    /**
     * LOGIN API (3 wrong attempts + 3 min lock + auto unlock)
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $account = Account::where('email', $request->email)->first();

        if (!$account) {
            return response()->json([
                'status' => false,
                'message' => 'Account not found'
            ], 404);
        }

        /* -----------------------------
           CHECK ACCOUNT LOCKED
        ------------------------------*/
        if ($account->locked_at) {

            $unlockTime = Carbon::parse($account->locked_at)->addMinutes(3);

            if (now()->lt($unlockTime)) {

                LoginAttempt::create([
                    'email' => $request->email,
                    'ip_address' => $request->ip(),
                    'status' => 'blocked'
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Account locked due to 3 wrong attempts. Please wait 3 minutes and try again.',
                    'unlock_at' => $unlockTime->format('Y-m-d H:i:s')
                ], 403);
            }

            // AUTO UNLOCK AFTER 3 MINUTES
            $account->update([
                'wrong_attempts' => 0,
                'locked_at' => null
            ]);
        }

        /* -----------------------------
           WRONG PASSWORD
        ------------------------------*/
        if (!Hash::check($request->password, $account->password)) {

            $account->increment('wrong_attempts');

            LoginAttempt::create([
                'email' => $request->email,
                'ip_address' => $request->ip(),
                'status' => 'failed'
            ]);

            if ($account->wrong_attempts >= 3) {

                $account->update([
                    'locked_at' => now(),
                    'wrong_attempts' => 3
                ]);

                LoginAttempt::create([
                    'email' => $request->email,
                    'ip_address' => $request->ip(),
                    'status' => 'blocked'
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Account locked due to 3 wrong attempts. Please wait 3 minutes and try again.'
                ], 403);
            }

            return response()->json([
                'status' => false,
                'message' => 'Invalid password',
                'wrong_attempts' => $account->wrong_attempts
            ], 401);
        }

        /* -----------------------------
           SUCCESS LOGIN
        ------------------------------*/
        $account->update([
            'wrong_attempts' => 0
        ]);

        LoginAttempt::create([
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'status' => 'success'
        ]);

        $token = auth()->login($account);

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $token,
            'data' => $account
        ]);
    }

    /**
     * ADMIN - LOCKED ACCOUNTS
     */
    public function lockedAccounts()
    {
        return response()->json([
            'status' => true,
            'data' => Account::whereNotNull('locked_at')->get()
        ]);
    }
}