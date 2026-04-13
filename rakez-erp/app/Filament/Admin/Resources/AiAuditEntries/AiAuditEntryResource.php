<?php

namespace App\Filament\Admin\Resources\AiAuditEntries;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\AiAuditEntries\Pages\ListAiAuditEntries;
use App\Models\AiAuditEntry;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AiAuditEntryResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = AiAuditEntry::class;

    protected static string $viewPermission = 'ai.calls.view';

    protected static ?string $slug = 'ai-audit-entries';

    protected static ?string $navigationLabel = 'AI Audit Trail';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string | \UnitEnum | null $navigationGroup = 'AI & Knowledge';

    protected static ?int $navigationSort = 12;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('user')->latest('created_at'))
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.name')->label('User')->placeholder('-'),
                TextColumn::make('action')->badge(),
                TextColumn::make('resource_type')->label('Resource')->badge()->placeholder('-'),
                TextColumn::make('resource_id')->placeholder('-'),
                TextColumn::make('ip_address')->placeholder('-'),
                TextColumn::make('created_at')->dateTime(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiAuditEntries::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('AI & Knowledge', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('AI & Knowledge', static::$viewPermission);
    }
}
