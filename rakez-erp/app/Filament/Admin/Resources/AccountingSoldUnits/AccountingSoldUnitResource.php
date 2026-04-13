<?php

namespace App\Filament\Admin\Resources\AccountingSoldUnits;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\AccountingSoldUnits\Pages\ListAccountingSoldUnits;
use App\Filament\Admin\Resources\AccountingSoldUnits\Pages\ViewAccountingSoldUnit;
use App\Models\SalesReservation;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccountingSoldUnitResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = SalesReservation::class;

    protected static string $viewPermission = 'accounting.sold-units.view';

    protected static ?string $slug = 'accounting-sold-units';

    protected static ?string $navigationLabel = 'Sold Units';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string | \UnitEnum | null $navigationGroup = 'Accounting & Finance';

    protected static ?int $navigationSort = 9;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['contract', 'contractUnit', 'marketingEmployee', 'titleTransfer', 'commission'])
                ->withCount('deposits')
                ->where('status', 'confirmed')
                ->latest('confirmed_at'))
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('contract.project_name')->label('Project')->searchable()->placeholder('-'),
                TextColumn::make('contractUnit.unit_number')->label('Unit')->placeholder('-'),
                TextColumn::make('client_name')->label('Client')->searchable()->placeholder('-'),
                TextColumn::make('marketingEmployee.name')->label('Sales Owner')->placeholder('-'),
                TextColumn::make('purchase_mechanism')->label('Purchase')->badge(),
                TextColumn::make('credit_status')->label('Credit Status')->badge(),
                IconColumn::make('down_payment_confirmed')->label('Deposit Confirmed')->boolean(),
                TextColumn::make('down_payment_amount')->label('Down Payment')->money('AED'),
                TextColumn::make('deposits_count')->label('Deposits')->sortable(),
                TextColumn::make('titleTransfer.status')->label('Title Transfer')->badge()->placeholder('-'),
                TextColumn::make('confirmed_at')->dateTime()->sortable(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->filters([
                SelectFilter::make('purchase_mechanism')
                    ->options([
                        'cash' => 'Cash',
                        'supported_bank' => 'Supported Bank',
                        'unsupported_bank' => 'Unsupported Bank',
                    ]),
                SelectFilter::make('credit_status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'title_transfer' => 'Title Transfer',
                        'sold' => 'Sold',
                        'rejected' => 'Rejected',
                    ]),
                TernaryFilter::make('down_payment_confirmed')
                    ->label('Deposit Confirmed'),
                Filter::make('confirmed_date_range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('confirmed_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('confirmed_at', '<=', $date));
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Booking')
                ->schema([
                    TextEntry::make('id'),
                    TextEntry::make('contract.project_name')->label('Project')->placeholder('-'),
                    TextEntry::make('contractUnit.unit_number')->label('Unit')->placeholder('-'),
                    TextEntry::make('client_name')->label('Client')->placeholder('-'),
                    TextEntry::make('marketingEmployee.name')->label('Sales Owner')->placeholder('-'),
                    TextEntry::make('purchase_mechanism')->label('Purchase')->badge(),
                    TextEntry::make('credit_status')->label('Credit')->badge(),
                    IconEntry::make('down_payment_confirmed')->label('Deposit Confirmed')->boolean(),
                    TextEntry::make('down_payment_amount')->label('Down Payment')->money('AED')->placeholder('-'),
                    TextEntry::make('confirmed_at')->dateTime()->placeholder('-'),
                ])
                ->columns(2),
            Section::make('Commission')
                ->visible(fn (SalesReservation $record): bool => $record->commission !== null)
                ->schema([
                    TextEntry::make('commission.status')->label('Status')->badge(),
                    TextEntry::make('commission.commission_source')->label('Source')->badge(),
                    TextEntry::make('commission.final_selling_price')->label('Final Selling Price')->money('AED'),
                    TextEntry::make('commission.commission_percentage')->label('Commission %')->suffix('%'),
                    TextEntry::make('commission.net_amount')->label('Net Amount')->money('AED'),
                    TextEntry::make('commission.team_responsible')->label('Team')->placeholder('-'),
                ])
                ->columns(2),
            Section::make('Title transfer')
                ->visible(fn (SalesReservation $record): bool => $record->titleTransfer !== null)
                ->schema([
                    TextEntry::make('titleTransfer.status')->label('Status')->badge(),
                ])
                ->columns(2),
        ]);
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Accounting & Finance', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Accounting & Finance', static::$viewPermission);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountingSoldUnits::route('/'),
            'view' => ViewAccountingSoldUnit::route('/{record}'),
        ];
    }
}
