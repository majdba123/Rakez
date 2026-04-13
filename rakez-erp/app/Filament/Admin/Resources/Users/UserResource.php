<?php

namespace App\Filament\Admin\Resources\Users;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\Team;
use App\Models\User;
use App\Services\Governance\EffectiveAccessSnapshotService;
use App\Services\Governance\GovernanceCatalog;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

class UserResource extends Resource
{
    use HasGovernanceAuthorization;

    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Users';

    protected static string | \UnitEnum | null $navigationGroup = 'Access Governance';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        $catalog = app(GovernanceCatalog::class);

        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('phone')
                ->tel()
                ->maxLength(20)
                ->unique(ignoreRecord: true),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->minLength(8)
                ->dehydrated(fn (?string $state): bool => filled($state)),
            Select::make('type')
                ->required()
                ->searchable()
                ->options($catalog->userTypeOptions()),
            Toggle::make('is_manager')
                ->label('Manager')
                ->default(false),
            Select::make('team_id')
                ->label('Team')
                ->searchable()
                ->preload()
                ->options(fn (): array => Team::query()->orderBy('name')->pluck('name', 'id')->all()),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
            Select::make('governance_roles')
                ->label('Governance Roles')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(fn (): array => $catalog->assignableGovernanceRoleOptions(auth()->user()))
                ->helperText('Overlay roles for Filament governance only.'),
            Select::make('direct_permissions')
                ->label('Direct Permissions')
                ->multiple()
                ->searchable()
                ->preload()
                ->options($catalog->groupedPermissionOptions())
                ->helperText('Grant or revoke any individual permission directly for this user.')
                ->columnSpanFull(),
            Placeholder::make('effective_access_summary')
                ->label('Effective Access Snapshot')
                ->content(fn (?User $record): string => $record ? app(EffectiveAccessSnapshotService::class)->summaryForUser($record) : 'Available after the user is created.')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withTrashed()->with(['roles', 'team'])->withCount('permissions'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge(),
                IconColumn::make('is_manager')
                    ->label('Manager')
                    ->boolean(),
                TextColumn::make('team.name')
                    ->label('Team')
                    ->toggleable(),
                TextColumn::make('governance_roles')
                    ->label('Governance Roles')
                    ->state(fn (User $record): string => $record->roles->pluck('name')->intersect(config('governance.managed_panel_roles'))->implode(', ') ?: '-'),
                TextColumn::make('permissions_count')
                    ->label('Direct Permissions')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('deleted_at')
                    ->label('Deleted')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
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
        return static::canAccessGovernancePage('Access Governance', 'admin.users.view');
    }

    public static function canCreate(): bool
    {
        return static::canGovernanceMutation('admin.users.manage');
    }

    public static function canEdit(Model $record): bool
    {
        if (! static::canGovernanceMutation('admin.users.manage')) {
            return false;
        }

        if ($record instanceof User && method_exists($record, 'trashed') && $record->trashed()) {
            return false;
        }

        return ! static::isProtectedSuperAdmin($record);
    }

    public static function canDelete(Model $record): bool
    {
        if (! static::canGovernanceMutation('admin.users.manage')) {
            return false;
        }

        if ($record instanceof User && method_exists($record, 'trashed') && $record->trashed()) {
            return false;
        }

        return ! static::isProtectedSuperAdmin($record);
    }

    public static function canRestore(Model $record): bool
    {
        if (! static::canGovernanceMutation('admin.users.manage')) {
            return false;
        }

        if (! ($record instanceof User) || ! method_exists($record, 'trashed') || ! $record->trashed()) {
            return false;
        }

        return ! static::isProtectedSuperAdmin($record);
    }

    protected static function isProtectedSuperAdmin(Model $record): bool
    {
        $superAdminRole = config('governance.super_admin_role', 'super_admin');

        if (! $record instanceof User || ! $record->hasRole($superAdminRole)) {
            return false;
        }

        $actor = auth()->user();

        return ! ($actor instanceof User && $actor->hasRole($superAdminRole));
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }
}

