<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;

class AdminHome extends Dashboard
{
    use HasGovernanceAuthorization;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Admin Home';

    protected static string | \UnitEnum | null $navigationGroup = 'Overview';

    protected static ?int $navigationSort = -100;

    public static function canAccess(): bool
    {
        return static::canAccessGovernancePage('Overview', 'admin.dashboard.view');
    }
}
