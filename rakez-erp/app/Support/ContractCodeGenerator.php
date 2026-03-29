<?php

namespace App\Support;

use App\Models\City;
use App\Models\Contract;

/**
 * Builds a short reference code for a contract: first letter of type + city code + side (N/W/E/S or X).
 * Ensures uniqueness against existing contracts (including soft-deleted).
 */
final class ContractCodeGenerator
{
    private const PLACEHOLDER = 'X';

    private const SIDES = ['N', 'W', 'E', 'S'];

    /**
     * Merge a unique `code` into $data (overwrites any client-supplied `code`).
     * Requires `city_id` and a City row with a usable `code`.
     *
     * @param  array<string, mixed>  $data
     */
    public static function assignCodeToDataArray(array &$data): void
    {
        unset($data['code']);

        $cityId = $data['city_id'] ?? null;
        if (!$cityId) {
            throw new \InvalidArgumentException('city_id is required to generate contract code.');
        }

        $city = City::query()->find($cityId);
        if (!$city) {
            throw new \InvalidArgumentException('City not found for contract code generation.');
        }

        $base = self::buildBase(
            isset($data['contract_type']) ? (string) $data['contract_type'] : null,
            $city->code,
            isset($data['side']) ? (string) $data['side'] : null
        );

        $data['code'] = self::allocateUnique($base);
    }

    public static function buildBase(?string $contractType, ?string $cityCode, ?string $side): string
    {
        $typePart = self::firstLetterFromType($contractType);
        $cityPart = self::sanitizeCityCode($cityCode);
        $sidePart = self::normalizeSide($side);

        return $typePart.$cityPart.$sidePart;
    }

    public static function allocateUnique(string $base): string
    {
        $code = $base;
        $n = 1;
        while (Contract::withTrashed()->where('code', $code)->exists()) {
            $code = $base.'-'.$n;
            $n++;
            if ($n > 9999) {
                return $base.'-'.substr(str_replace('.', '', uniqid('', true)), -10);
            }
        }

        return $code;
    }

    private static function firstLetterFromType(?string $contractType): string
    {
        $trimmed = trim((string) $contractType);
        if ($trimmed === '') {
            return self::PLACEHOLDER;
        }

        $first = mb_substr($trimmed, 0, 1, 'UTF-8');

        return $first !== '' ? mb_strtoupper($first, 'UTF-8') : self::PLACEHOLDER;
    }

    private static function sanitizeCityCode(?string $cityCode): string
    {
        $raw = strtoupper(preg_replace('/\s+/', '', (string) $cityCode));
        $raw = preg_replace('/[^A-Z0-9]/', '', $raw) ?? '';

        return $raw !== '' ? $raw : 'XX';
    }

    private static function normalizeSide(?string $side): string
    {
        $s = $side === null ? '' : strtoupper(trim($side));

        return in_array($s, self::SIDES, true) ? $s : self::PLACEHOLDER;
    }
}
