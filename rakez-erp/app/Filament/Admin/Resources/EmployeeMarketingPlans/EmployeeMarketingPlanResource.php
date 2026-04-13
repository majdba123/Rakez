<?php

namespace App\Filament\Admin\Resources\EmployeeMarketingPlans;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\EmployeeMarketingPlans\Pages\ListEmployeeMarketingPlans;
use App\Models\EmployeeMarketingPlan;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeMarketingPlanResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = EmployeeMarketingPlan::class;

    protected static string $viewPermission = 'marketing.projects.view';

    protected static ?string $slug = 'employee-marketing-plans';

    protected static ?string $navigationLabel = 'Employee Plans';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string | \UnitEnum | null $navigationGroup = 'Marketing Oversight';

    protected static ?int $navigationSort = 12;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['marketingProject.contract', 'user'])->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('marketingProject.contract.project_name')->label('Project')->searchable()->placeholder('-'),
                TextColumn::make('user.name')->label('Employee')->placeholder('-'),
                TextColumn::make('commission_value')->money('AED'),
                TextColumn::make('marketing_value')->money('AED'),
                TextColumn::make('marketing_percent')->suffix('%'),
                TextColumn::make('direct_contact_percent')->suffix('%'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeMarketingPlans::route('/'),
        ];
    }
}
