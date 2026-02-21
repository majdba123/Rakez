<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordMail;
use App\Mail\SendOtpMail;
use Illuminate\Support\Facades\Password;

use App\Models\User;

class OtpHelper
{
    /** OTP cache TTL in minutes. */
    private const OTP_CACHE_MINUTES = 5;

    /** Verified state cache TTL in minutes. */
    private const VERIFIED_CACHE_MINUTES = 30;

    /** Max verify attempts before invalidating OTP. */
    private const MAX_VERIFY_ATTEMPTS = 5;

    public static function sendOtpEmail(int $id): string
    {
        $user = User::findOrFail($id);
        $otp = (string) random_int(100000, 999999);

        Cache::put(
            self::otpCacheKey($user->id),
            [
                'hash' => Hash::make($otp),
                'email' => $user->email,
                'attempts' => 0,
            ],
            now()->addMinutes(self::OTP_CACHE_MINUTES)
        );

        Mail::to($user->email)->send(new SendOtpMail($otp));

        return $otp;
    }

    public static function verifyOtp(int $id, string $otp): bool
    {
        $payload = Cache::get(self::otpCacheKey($id));
        if (!is_array($payload) || empty($payload['hash'])) {
            return false;
        }

        $attempts = (int) ($payload['attempts'] ?? 0);
        if ($attempts >= self::MAX_VERIFY_ATTEMPTS) {
            Cache::forget(self::otpCacheKey($id));
            return false;
        }

        $valid = Hash::check($otp, (string) $payload['hash']);
        if (!$valid) {
            $payload['attempts'] = $attempts + 1;
            Cache::put(self::otpCacheKey($id), $payload, now()->addMinutes(self::OTP_CACHE_MINUTES));
            return false;
        }

        Cache::forget(self::otpCacheKey($id));
        Cache::put(self::verifiedCacheKey($id), true, now()->addMinutes(self::VERIFIED_CACHE_MINUTES));

        return true;
    }

    public static function isOtpVerified(int $id): bool
    {
        return Cache::get(self::verifiedCacheKey($id), false) === true;
    }

    public static function sendPasswordResetLink(int $id): string
    {
        $user = User::findOrFail($id);
        $token = Password::broker()->createToken($user);

        $baseUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $resetUrl = $baseUrl . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($user->email);

        Mail::to($user->email)->send(new ResetPasswordMail($resetUrl));

        return $token;
    }

    /**
     * Set user password directly by user ID (no token flow).
     *
     * @deprecated Use sendPasswordResetLink() to email a reset link, then resetPasswordWithToken()
     *             for the user to reset with the token. This method will be removed in a future release.
     */
    public static function resetPassword(int $id, string $newPassword): void
    {
        trigger_error(
            'OtpHelper::resetPassword() is deprecated. Use sendPasswordResetLink() and resetPasswordWithToken() instead.',
            E_USER_DEPRECATED
        );
        $user = User::findOrFail($id);
        $user->password = Hash::make($newPassword);
        $user->save();
    }

    public static function resetPasswordWithToken(string $email, string $token, string $newPassword): string
    {
        return Password::reset(
            [
                'email' => $email,
                'token' => $token,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );
    }

    private static function otpCacheKey(int $id): string
    {
        return 'otp:user:' . $id;
    }

    private static function verifiedCacheKey(int $id): string
    {
        return 'otp:verified:' . $id;
    }
}
