<?php

namespace App\Filament\Admin\Resources\DirectPermissions;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\DirectPermissions\Pages\EditDirectPermissions;
use App\Filament\Admin\Resources\DirectPermissions\Pages\ListDirectPermissions;
use App\Models\User;
use App\Services\Governance\EffectiveAccessSnapshotService;
use App\Services\Governance\GovernanceAccessService;
use App\Services\Governance\GovernanceCatalog;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DirectPermissionResource extends Resource
{
    use HasGovernanceAuthorization;

    protected static ?string $model = User::class;

    protected static ?string $slug = 'direct-permissions';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $navigationLabel = 'Direct Permissions';

    protected static string | \UnitEnum | null $navigationGroup = 'Access Governance';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Placeholder::make('user_name')
                ->label('User')
                ->content(fn (?User $record): string => $record?->name ?? '-'),
            Placeholder::make('user_email')
                ->label('Email')
                ->content(fn (?User $record): string => $record?->email ?? '-'),
            Placeholder::make('role_summary')
                ->label('Current Roles')
                ->content(fn (?User $record): string => $record ? $record->roles->pluck('name')->implode(', ') : '-'),
            Select::make('direct_permissions')
                ->label('Direct Permissions')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(app(GovernanceCatalog::class)->groupedPermissionOptions()),
            Placeholder::make('effective_access_summary')
                ->label('Effective Access Snapshot')
                ->content(fn (?User $record): string => $record ? app(EffectiveAccessSnapshotService::class)->summaryForUser($record) : '-')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withTrashed()->with(['roles'])->withCount('permissions'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('permissions_count')
                    ->label('Direct Permissions')
                    ->sortable(),
                TextColumn::make('roles_summary')
                    ->label('Roles')
                    ->state(fn (User $record): string => $record->roles->pluck('name')->implode(', ') ?: '-'),
                IconColumn::make('panel_eligible')
                    ->label('Panel Access')
                    ->boolean()
                    ->state(fn (User $record): bool => app(GovernanceAccessService::class)->canAccessPanel($record)),
                IconColumn::make('is_trashed')
                    ->label('Deleted')
                    ->boolean()
                    ->state(fn (User $record): bool => method_exists($record, 'trashed') && $record->trashed()),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDirectPermissions::route('/'),
            'edit' => EditDirectPermissions::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Access Governance', 'admin.direct_permissions.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        if (! static::canGovernanceMutation('admin.direct_permissions.manage')) {
            return false;
        }

        if (! $record instanceof User || (method_exists($record, 'trashed') && $record->trashed())) {
            return false;
        }

        $superAdminRole = config('governance.super_admin_role', 'super_admin');
        $actor = auth()->user();

        if ($record->hasRole($superAdminRole)) {
            return $actor instanceof User && $actor->hasRole($superAdminRole);
        }

        return true;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}

