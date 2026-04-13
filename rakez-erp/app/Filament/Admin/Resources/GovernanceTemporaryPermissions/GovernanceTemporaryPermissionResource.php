<?php

namespace App\Filament\Admin\Resources\GovernanceTemporaryPermissions;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\GovernanceTemporaryPermissions\Pages\CreateGovernanceTemporaryPermission;
use App\Filament\Admin\Resources\GovernanceTemporaryPermissions\Pages\ListGovernanceTemporaryPermissions;
use App\Models\GovernanceTemporaryPermission;
use App\Models\User;
use App\Services\Governance\GovernanceCatalog;
use App\Services\Governance\GovernanceTemporaryPermissionService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GovernanceTemporaryPermissionResource extends Resource
{
    use HasGovernanceAuthorization;

    protected static ?string $model = GovernanceTemporaryPermission::class;

    protected static ?string $slug = 'governance-temporary-permissions';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Temporary Permissions';

    protected static string | \UnitEnum | null $navigationGroup = 'Access Governance';

    protected static ?int $navigationSort = 45;

    public static function form(Schema $schema): Schema
    {
        $catalog = app(GovernanceCatalog::class);

        return $schema->components([
            Select::make('user_id')
                ->label('User')
                ->relationship('user', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('permission')
                ->options($catalog->permissionOptions())
                ->searchable()
                ->required(),
            DateTimePicker::make('expires_at')
                ->required()
                ->native(false)
                ->minDate(now()),
            Textarea::make('reason')
                ->rows(3)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'grantedBy'])->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.name')->label('User')->searchable(),
                TextColumn::make('user.email')->label('Email')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('permission')->searchable(),
                TextColumn::make('expires_at')->dateTime()->sortable(),
                TextColumn::make('revoked_at')->dateTime()->placeholder('—'),
                TextColumn::make('grantedBy.name')->label('Granted By')->placeholder('—'),
            ])
            ->actions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (GovernanceTemporaryPermission $record): bool => $record->revoked_at === null
                        && static::canGovernanceMutation('admin.temp_permissions.manage'))
                    ->action(function (GovernanceTemporaryPermission $record): void {
                        abort_unless(static::canGovernanceMutation('admin.temp_permissions.manage'), 403);

                        $actor = auth()->user();
                        if (! $actor instanceof User) {
                            abort(403);
                        }

                        app(GovernanceTemporaryPermissionService::class)->revoke($record, $actor);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGovernanceTemporaryPermissions::route('/'),
            'create' => CreateGovernanceTemporaryPermission::route('/create'),
        ];
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        if (! app(GovernanceTemporaryPermissionService::class)->isEnabled()) {
            return false;
        }

        return static::canAccessGovernancePage('Access Governance', 'admin.temp_permissions.view');
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        if (! app(GovernanceTemporaryPermissionService::class)->isEnabled()) {
            return false;
        }

        return static::canGovernanceMutation('admin.temp_permissions.manage');
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
