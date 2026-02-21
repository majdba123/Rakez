<?php

namespace Tests\Unit\Helpers;

use App\Helpers\OtpHelper;
use App\Mail\ResetPasswordMail;
use App\Mail\SendOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_otp_email_stores_hashed_otp_and_sends_mail(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $otp = OtpHelper::sendOtpEmail($user->id);

        $payload = Cache::get('otp:user:' . $user->id);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('hash', $payload);
        $this->assertArrayHasKey('attempts', $payload);
        $this->assertArrayNotHasKey('otp', $payload);
        $this->assertSame(0, $payload['attempts']);

        $this->assertTrue(OtpHelper::verifyOtp($user->id, $otp));
        $this->assertTrue(OtpHelper::isOtpVerified($user->id));

        Mail::assertSent(SendOtpMail::class);
    }

    public function test_verify_otp_increments_attempts_on_failure(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        OtpHelper::sendOtpEmail($user->id);
        $this->assertFalse(OtpHelper::verifyOtp($user->id, '000000'));

        $payload = Cache::get('otp:user:' . $user->id);
        $this->assertSame(1, $payload['attempts']);
    }

    public function test_send_password_reset_link_uses_tokenized_mail(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $token = OtpHelper::sendPasswordResetLink($user->id);

        $this->assertNotEmpty($token);
        Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) use ($user): bool {
            return str_contains($mail->resetUrl, 'token=')
                && str_contains($mail->resetUrl, urlencode($user->email));
        });
    }
}
