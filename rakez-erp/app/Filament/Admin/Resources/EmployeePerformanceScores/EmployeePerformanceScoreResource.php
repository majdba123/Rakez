<?php

namespace App\Filament\Admin\Resources\EmployeePerformanceScores;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\EmployeePerformanceScores\Pages\ListEmployeePerformanceScores;
use App\Models\EmployeePerformanceScore;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeePerformanceScoreResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = EmployeePerformanceScore::class;

    protected static string $viewPermission = 'hr.performance.view';

    protected static ?string $slug = 'employee-performance-scores';

    protected static ?string $navigationLabel = 'Performance Scores';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string | \UnitEnum | null $navigationGroup = 'HR Oversight';

    protected static ?int $navigationSort = 11;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user')->latest('period_end'))
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.name')->label('Employee')->searchable()->placeholder('-'),
                TextColumn::make('composite_score')->label('Score')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('period_start')->date(),
                TextColumn::make('period_end')->date(),
                TextColumn::make('created_at')->since(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeePerformanceScores::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('HR Oversight', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('HR Oversight', static::$viewPermission);
    }
}
