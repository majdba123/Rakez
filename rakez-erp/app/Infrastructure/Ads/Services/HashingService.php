<?php

namespace App\Infrastructure\Ads\Services;

use App\Domain\Ads\ValueObjects\Platform;

final class HashingService
{
    /**
     * Normalize and SHA-256 hash an email address.
     * Rules: trim whitespace, lowercase, then hash.
     */
    public function hashEmail(string $email): string
    {
        $normalized = strtolower(trim($email));

        return hash('sha256', $normalized);
    }

    /**
     * Normalize and SHA-256 hash a phone number.
     * Rules: include country code, remove non-numeric chars, strip leading double-zero, then hash.
     */
    public function hashPhone(string $phone): string
    {
        $normalized = preg_replace('/[^\d]/', '', $phone);
        $normalized = preg_replace('/^00/', '', $normalized);

        return hash('sha256', $normalized);
    }

    /**
     * Normalize and hash a name (first or last).
     * Rules: lowercase, remove punctuation, UTF-8 encode, then hash.
     */
    public function hashName(string $name): string
    {
        $normalized = mb_strtolower(trim($name), 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}]/u', '', $normalized);

        return hash('sha256', $normalized);
    }

    /**
     * Normalize and hash a city name.
     * Rules: lowercase, remove punctuation and spaces, UTF-8 encode, then hash.
     */
    public function hashCity(string $city): string
    {
        $normalized = mb_strtolower(trim($city), 'UTF-8');
        $normalized = preg_replace('/[\s\p{P}]/u', '', $normalized);

        return hash('sha256', $normalized);
    }

    /**
     * Hash a state (2-char ANSI lowercase).
     */
    public function hashState(string $state): string
    {
        $normalized = strtolower(trim($state));
        $normalized = preg_replace('/[\s\p{P}]/u', '', $normalized);

        return hash('sha256', $normalized);
    }

    /**
     * Hash a zip code. US: first 5 digits only; lowercase, remove spaces/hyphens.
     */
    public function hashZip(string $zip): string
    {
        $normalized = strtolower(trim($zip));
        $normalized = str_replace([' ', '-'], '', $normalized);

        return hash('sha256', $normalized);
    }

    /**
     * Hash a gender (f/m lowercase).
     */
    public function hashGender(string $gender): string
    {
        $normalized = strtolower(trim($gender));

        return hash('sha256', $normalized);
    }

    /**
     * Hash country (2-letter ISO lowercase).
     */
    public function hashCountry(string $country): string
    {
        $normalized = strtolower(trim($country));

        return hash('sha256', $normalized);
    }

    /**
     * Hash an external ID (SHA-256). Meta recommends hashing; Snap recommends; TikTok requires.
     */
    public function hashExternalId(string $id): string
    {
        return hash('sha256', trim($id));
    }

    /**
     * Generic hash for an arbitrary pre-normalized value.
     */
    public function sha256(string $value): string
    {
        return hash('sha256', $value);
    }
}
