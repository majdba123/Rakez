<?php

namespace App\Services\Registration;

use App\Helpers\OtpHelper;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SocialAuthService
{
    /**
     * Handle social provider callback: find or create user, send OTP, login, return JSON response.
     *
     * @param object $socialUser Socialite user (getEmail(), getName(), id)
     * @param string $providerIdColumn User table column for provider id (e.g. 'google_id', 'facebook_id')
     * @param string $successMessage Message to return in response
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleCallback(object $socialUser, string $providerIdColumn, string $successMessage = 'User login successfully')
    {
        $user = User::where('email', $socialUser->getEmail())->first();

        if (!$user) {
            $user = User::create([
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'password' => Hash::make(Str::random(32)),
                $providerIdColumn => $socialUser->id,
            ]);
        }

        OtpHelper::sendOtpEmail($user->id);
        Auth::login($user, true);
        $token = $user->createToken($user->name . '-AuthToken')->plainTextToken;

        return response()->json([
            'message' => $successMessage,
            'access_token' => $token,
            'user' => $user,
        ]);
    }
}
