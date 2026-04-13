<?php

namespace App\Filament\Admin\Resources\Roles;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\Roles\Pages\EditRole;
use App\Filament\Admin\Resources\Roles\Pages\ListRoles;
use App\Services\Governance\GovernanceCatalog;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    use HasGovernanceAuthorization;

    protected static ?string $model = Role::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Roles';

    protected static string | \UnitEnum | null $navigationGroup = 'Access Governance';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->disabled()
                ->dehydrated(false),
            Placeholder::make('role_category')
                ->label('Category')
                ->content(function (?Role $record): string {
                    if (! $record) {
                        return 'Governance';
                    }

                    $catalog = app(GovernanceCatalog::class);

                    if ($catalog->isOperationalRole($record->name)) {
                        return 'Legacy operational role';
                    }

                    if ($catalog->isManagedGovernanceRole($record->name)) {
                        return 'Governance overlay role';
                    }

                    if (in_array($record->name, config('governance.future_section_roles'), true)) {
                        return 'Future section governance role';
                    }

                    return 'System role';
                }),
            Select::make('permissions')
                ->label('Permissions')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(app(GovernanceCatalog::class)->groupedPermissionOptions())
                ->helperText('Permissions come from the frozen dictionary only.'),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['users', 'permissions']))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('Users')
                    ->sortable(),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->sortable(),
                TextColumn::make('role_category')
                    ->label('Category')
                    ->state(function (Role $record): string {
                        $catalog = app(GovernanceCatalog::class);

                        if ($catalog->isOperationalRole($record->name)) {
                            return 'Legacy';
                        }

                        if ($catalog->isManagedGovernanceRole($record->name)) {
                            return 'Governance';
                        }

                        if (in_array($record->name, config('governance.future_section_roles'), true)) {
                            return 'Future Section';
                        }

                        return 'System';
                    }),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Access Governance', 'admin.roles.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        if (! static::canGovernanceMutation('admin.roles.manage')) {
            return false;
        }

        if (! app(GovernanceCatalog::class)->isEditableRole($record->name)) {
            return false;
        }

        $superAdminRole = config('governance.super_admin_role', 'super_admin');
        if ($record->name === $superAdminRole) {
            $actor = auth()->user();

            return $actor instanceof \App\Models\User && $actor->hasRole($superAdminRole);
        }

        return true;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}

