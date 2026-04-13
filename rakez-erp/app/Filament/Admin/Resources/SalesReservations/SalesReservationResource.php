<?php

namespace App\Filament\Admin\Resources\SalesReservations;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\SalesReservations\Pages\ListSalesReservations;
use App\Filament\Admin\Resources\SalesReservations\Pages\ViewSalesReservation;
use App\Models\SalesReservation;
use App\Models\User;
use App\Services\Governance\GovernanceAuditLogger;
use App\Services\Sales\SalesReservationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SalesReservationResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = SalesReservation::class;

    protected static string $viewPermission = 'sales.reservations.view';

    protected static ?string $slug = 'sales-reservations';

    protected static ?string $navigationLabel = 'Sales Reservations';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string | \UnitEnum | null $navigationGroup = 'Sales Oversight';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['contract', 'contractUnit', 'marketingEmployee'])->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('contract.project_name')->label('Project')->searchable()->placeholder('-'),
                TextColumn::make('contractUnit.unit_number')->label('Unit')->placeholder('-'),
                TextColumn::make('marketingEmployee.name')->label('Owner')->placeholder('-'),
                TextColumn::make('client_name')->label('Client')->searchable()->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('purchase_mechanism')->label('Purchase')->badge(),
                IconColumn::make('down_payment_confirmed')->label('Deposit Confirmed')->boolean(),
                TextColumn::make('credit_status')->label('Credit')->badge(),
                TextColumn::make('confirmed_at')->dateTime()->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'under_negotiation' => 'Under Negotiation',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                    ]),
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
            ])
            ->actions([
                ViewAction::make(),
                Action::make('confirmReservation')
                    ->label('Confirm')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (SalesReservation $record): bool => static::canGovernanceMutation('sales.reservations.confirm') && $record->canConfirm())
                    ->action(function (SalesReservation $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $updated = app(SalesReservationService::class)->confirmReservation($record->id, $actor);

                        app(GovernanceAuditLogger::class)->log('governance.sales.reservation.confirmed', $updated, [
                            'before' => ['status' => $record->status],
                            'after' => [
                                'status' => $updated->status,
                                'confirmed_at' => optional($updated->confirmed_at)?->toDateTimeString(),
                            ],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Reservation confirmed.')
                            ->send();
                    }),
                Action::make('cancelReservation')
                    ->label('Cancel')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (SalesReservation $record): bool => static::canGovernanceMutation('sales.reservations.cancel') && $record->canCancel())
                    ->action(function (SalesReservation $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $updated = app(SalesReservationService::class)->cancelReservation($record->id, null, $actor);

                        app(GovernanceAuditLogger::class)->log('governance.sales.reservation.cancelled', $updated, [
                            'before' => ['status' => $record->status],
                            'after' => [
                                'status' => $updated->status,
                                'cancelled_at' => optional($updated->cancelled_at)?->toDateTimeString(),
                            ],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Reservation cancelled.')
                            ->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Reservation')
                ->schema([
                    TextEntry::make('id'),
                    TextEntry::make('contract.project_name')->label('Project')->placeholder('-'),
                    TextEntry::make('contractUnit.unit_number')->label('Unit')->placeholder('-'),
                    TextEntry::make('marketingEmployee.name')->label('Owner')->placeholder('-'),
                    TextEntry::make('client_name')->label('Client')->placeholder('-'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('purchase_mechanism')->label('Purchase')->badge(),
                    TextEntry::make('credit_status')->label('Credit')->badge(),
                    IconEntry::make('down_payment_confirmed')->label('Deposit Confirmed')->boolean(),
                    TextEntry::make('confirmed_at')->dateTime()->placeholder('-'),
                ])
                ->columns(2),
            Section::make('Financial Context')
                ->schema([
                    TextEntry::make('down_payment_amount')->label('Down Payment')->money('AED')->placeholder('-'),
                    TextEntry::make('payment_method')->label('Payment Method')->placeholder('-'),
                    TextEntry::make('proposed_price')->label('Proposed Price')->money('AED')->placeholder('-'),
                    TextEntry::make('client_mobile')->label('Client Mobile')->placeholder('-'),
                    TextEntry::make('created_at')->dateTime(),
                ])
                ->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesReservations::route('/'),
            'view' => ViewSalesReservation::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Sales Oversight', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Sales Oversight', static::$viewPermission);
    }
}
