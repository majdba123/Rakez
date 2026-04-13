<?php

namespace App\Filament\Admin\Resources\DeveloperMarketingPlans;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\DeveloperMarketingPlans\Pages\ListDeveloperMarketingPlans;
use App\Models\DeveloperMarketingPlan;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DeveloperMarketingPlanResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = DeveloperMarketingPlan::class;

    protected static string $viewPermission = 'marketing.projects.view';

    protected static ?string $slug = 'developer-marketing-plans';

    protected static ?string $navigationLabel = 'Developer Plans';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocument;

    protected static string | \UnitEnum | null $navigationGroup = 'Marketing Oversight';

    protected static ?int $navigationSort = 11;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('contract')->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('contract.project_name')->label('Project')->searchable()->placeholder('-'),
                TextColumn::make('marketing_value')->money('AED'),
                TextColumn::make('marketing_percent')->suffix('%'),
                TextColumn::make('average_cpm')->money('AED'),
                TextColumn::make('average_cpc')->money('AED'),
                TextColumn::make('expected_impressions')->numeric(),
                TextColumn::make('expected_clicks')->numeric(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeveloperMarketingPlans::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Marketing Oversight', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Marketing Oversight', static::$viewPermission);
    }
}
