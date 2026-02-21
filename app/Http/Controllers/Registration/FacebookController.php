<?php

namespace App\Http\Controllers\Registration;

use App\Http\Controllers\Controller;
use App\Services\Registration\SocialAuthService;
use Laravel\Socialite\Facades\Socialite;

class FacebookController extends Controller
{
    public function __construct(
        private SocialAuthService $socialAuthService
    ) {}

    public function redirect()
    {
        return Socialite::driver('facebook')->stateless()->redirect();
    }

    public function callback()
    {
        $facebookUser = Socialite::driver('facebook')->stateless()->user();

        return $this->socialAuthService->handleCallback(
            $facebookUser,
            'facebook_id',
            'User logged in successfully'
        );
    }
}
