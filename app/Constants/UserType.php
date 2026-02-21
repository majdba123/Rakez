<?php

namespace App\Constants;

final class UserType
{
    public const MARKETING = 'marketing';
    public const ADMIN = 'admin';
    public const PROJECT_ACQUISITION = 'project_acquisition';
    public const PROJECT_MANAGEMENT = 'project_management';
    public const EDITOR = 'editor';
    public const SALES = 'sales';
    public const ACCOUNTING = 'accounting';
    public const CREDIT = 'credit';
    public const HR = 'hr';

    /**
     * Legacy numeric mapping used by existing APIs.
     *
     * @return array<int, string>
     */
    public static function legacyMap(): array
    {
        return [
            0 => self::MARKETING,
            1 => self::ADMIN,
            2 => self::PROJECT_ACQUISITION,
            3 => self::PROJECT_MANAGEMENT,
            4 => self::EDITOR,
            5 => self::SALES,
            6 => self::ACCOUNTING,
            7 => self::CREDIT,
            8 => self::HR,
        ];
    }
}
