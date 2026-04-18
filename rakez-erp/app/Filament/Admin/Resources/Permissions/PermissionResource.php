<?php

namespace App\Filament\Admin\Resources\Permissions;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\Permissions\Pages\ListPermissions;
use App\Services\Governance\GovernanceCatalog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

class PermissionResource extends Resource
{
    use HasGovernanceAuthorization;

    protected static ?string $model = Permission::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Permissions';

    protected static string | \UnitEnum | null $navigationGroup = 'Access Governance';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        $catalog = app(GovernanceCatalog::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->whereIn('name', array_keys($catalog->panelPermissionDefinitions()))
                ->withCount(['roles', 'users']))
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-admin.resources.permissions.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label(__('filament-admin.resources.permissions.columns.description'))
                    ->state(fn (Permission $record): string => app(GovernanceCatalog::class)->permissionDescription($record->name) ?? '-')
                    ->wrap(),
                TextColumn::make('roles_count')
                    ->label(__('filament-admin.resources.permissions.columns.roles'))
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label(__('filament-admin.resources.permissions.columns.direct_users'))
                    ->sortable(),
                TextColumn::make('guard_name')
                    ->label(__('filament-admin.resources.permissions.columns.guard'))
                    ->badge(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermissions::route('/'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-admin.resources.permissions.navigation_label');
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Access Governance', 'admin.permissions.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
