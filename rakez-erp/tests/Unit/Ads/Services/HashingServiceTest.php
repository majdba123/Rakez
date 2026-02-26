<?php

namespace Tests\Unit\Ads\Services;

use App\Infrastructure\Ads\Services\HashingService;
use PHPUnit\Framework\TestCase;

class HashingServiceTest extends TestCase
{
    private HashingService $hasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hasher = new HashingService();
    }

    public function test_hash_email_lowercases_and_trims(): void
    {
        $hash1 = $this->hasher->hashEmail('  Test@Example.COM  ');
        $hash2 = $this->hasher->hashEmail('test@example.com');

        $this->assertSame($hash1, $hash2);
        $this->assertSame(hash('sha256', 'test@example.com'), $hash2);
    }

    public function test_hash_email_produces_64_char_hex(): void
    {
        $hash = $this->hasher->hashEmail('user@test.com');

        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    public function test_hash_phone_strips_non_numeric(): void
    {
        $hash1 = $this->hasher->hashPhone('+1 (555) 123-4567');
        $hash2 = $this->hasher->hashPhone('15551234567');

        $this->assertSame($hash1, $hash2);
    }

    public function test_hash_phone_removes_leading_double_zero(): void
    {
        $hash1 = $this->hasher->hashPhone('00971551234567');
        $hash2 = $this->hasher->hashPhone('971551234567');

        $this->assertSame($hash1, $hash2);
    }

    public function test_hash_name_lowercases_and_removes_punctuation(): void
    {
        $hash1 = $this->hasher->hashName("  O'Brien  ");
        $hash2 = $this->hasher->hashName('obrien');

        $this->assertSame($hash1, $hash2);
    }

    public function test_hash_name_handles_unicode(): void
    {
        $hash = $this->hasher->hashName('محمد');

        $this->assertSame(64, strlen($hash));
        $this->assertSame(hash('sha256', 'محمد'), $hash);
    }

    public function test_hash_city_removes_spaces_and_punctuation(): void
    {
        $hash1 = $this->hasher->hashCity('  New York  ');
        $hash2 = $this->hasher->hashCity('newyork');

        $this->assertSame($hash1, $hash2);
    }

    public function test_hash_state_lowercases_and_strips(): void
    {
        $hash1 = $this->hasher->hashState('  CA  ');
        $hash2 = $this->hasher->hashState('ca');

        $this->assertSame($hash1, $hash2);
    }

    public function test_hash_zip_removes_hyphens_and_spaces(): void
    {
        $hash1 = $this->hasher->hashZip('10001-1234');
        $hash2 = $this->hasher->hashZip('100011234');

        $this->assertSame($hash1, $hash2);
    }

    public function test_hash_gender_lowercases(): void
    {
        $hash1 = $this->hasher->hashGender('  F  ');
        $hash2 = $this->hasher->hashGender('f');

        $this->assertSame($hash1, $hash2);
    }

    public function test_hash_country_lowercases(): void
    {
        $hash1 = $this->hasher->hashCountry('  US  ');
        $hash2 = $this->hasher->hashCountry('us');

        $this->assertSame($hash1, $hash2);
    }

    public function test_hash_external_id_trims(): void
    {
        $hash1 = $this->hasher->hashExternalId('  cust_123  ');
        $hash2 = $this->hasher->hashExternalId('cust_123');

        $this->assertSame($hash1, $hash2);
    }

    public function test_sha256_matches_native_hash(): void
    {
        $input = 'test-value';
        $this->assertSame(hash('sha256', $input), $this->hasher->sha256($input));
    }

    public function test_deterministic_same_input_same_output(): void
    {
        $email = 'consistent@test.com';

        $this->assertSame(
            $this->hasher->hashEmail($email),
            $this->hasher->hashEmail($email),
        );
    }

    public function test_different_emails_produce_different_hashes(): void
    {
        $this->assertNotSame(
            $this->hasher->hashEmail('a@test.com'),
            $this->hasher->hashEmail('b@test.com'),
        );
    }
}
