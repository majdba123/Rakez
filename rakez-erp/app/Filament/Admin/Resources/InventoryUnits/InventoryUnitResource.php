<?php

namespace App\Filament\Admin\Resources\InventoryUnits;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\InventoryUnits\Pages\EditInventoryUnit;
use App\Filament\Admin\Resources\InventoryUnits\Pages\ListInventoryUnits;
use App\Models\ContractUnit;
use App\Models\User;
use App\Services\Contract\ContractUnitService;
use App\Services\Governance\GovernanceAuditLogger;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InventoryUnitResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = ContractUnit::class;

    protected static string $viewPermission = 'units.view';

    protected static ?string $slug = 'inventory-units';

    protected static ?string $navigationLabel = 'Inventory Units';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedSquaresPlus;

    protected static string | \UnitEnum | null $navigationGroup = 'Inventory Oversight';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('unit_type')->required()->maxLength(255),
            TextInput::make('unit_number')->required()->maxLength(255),
            Select::make('status')
                ->options([
                    'available' => 'Available',
                    'reserved' => 'Reserved',
                    'pending' => 'Pending',
                    'sold' => 'Sold',
                ])
                ->required(),
            TextInput::make('price')->numeric()->required(),
            TextInput::make('area')->numeric(),
            TextInput::make('floor')->maxLength(255),
            TextInput::make('bedrooms')->numeric(),
            TextInput::make('bathrooms')->numeric(),
            TextInput::make('private_area_m2')->numeric(),
            TextInput::make('facade')->label('View')->maxLength(100),
            Textarea::make('description_en')->maxLength(1000),
            Textarea::make('description_ar')->maxLength(1000),
            TextInput::make('diagrames')->maxLength(2000),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['contract'])->withCount('activeSalesReservations')->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('contract.project_name')->label('Project')->searchable()->placeholder('-'),
                TextColumn::make('unit_number')->label('Unit')->searchable(),
                TextColumn::make('unit_type')->label('Type')->badge()->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('price')->money('AED'),
                TextColumn::make('area'),
                TextColumn::make('floor')->placeholder('-'),
                TextColumn::make('active_sales_reservations_count')->label('Active Reservations'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'available' => 'Available',
                        'reserved' => 'Reserved',
                        'booked' => 'Booked',
                        'sold' => 'Sold',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                Action::make('deleteUnit')
                    ->label('Delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ContractUnit $record): bool => static::canDelete($record))
                    ->action(function (ContractUnit $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        app(GovernanceAuditLogger::class)->log('governance.inventory.unit.deleted', $record, [
                            'before' => [
                                'contract_id' => $record->contract_id,
                                'unit_number' => $record->unit_number,
                                'status' => $record->status,
                            ],
                        ], $actor);

                        app(ContractUnitService::class)->deleteUnit($record->id);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryUnits::route('/'),
            'edit' => EditInventoryUnit::route('/{record}/edit'),
        ];
    }

    protected static function editPermission(): ?string
    {
        return 'units.edit';
    }

    protected static function deletePermission(): ?string
    {
        return 'units.edit';
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Inventory Oversight', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Inventory Oversight', static::$viewPermission);
    }
}
