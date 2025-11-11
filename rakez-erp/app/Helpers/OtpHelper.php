<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\SendOtpMail;
use App\Mail\ResetPasswordMail;

use App\Models\User;

class OtpHelper
{
    public static function sendOtpEmail($id)
    {
        $user = User::findOrFail($id);
        $otp = Str::random(6);

        // Store OTP in cache with ID and email, set expiration time (e.g., 5 minutes)
        Cache::put('otp_' . $user->id, ['otp' => $otp, 'email' => $user->email], 300);

        // Send OTP via email
        Mail::to($user->email)->send(new SendOtpMail($otp));
        return $otp;
    }


    public static function resetPassword($id, $newPassword)
    {
        $user = User::findOrFail($id);
        $user->password = bcrypt($newPassword);
        $user->save();
        Mail::to($user->email)->send(new ResetPasswordMail($newPassword));
    }

}
