<?php

namespace App\Filament\Admin\Resources\SalesTargets;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\SalesTargets\Pages\ListSalesTargets;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\Sales\SalesTargetService;
use App\Support\Governance\GovernanceStatusCatalog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SalesTargetResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = SalesTarget::class;

    protected static string $viewPermission = 'sales.targets.view';

    protected static ?string $slug = 'sales-targets';

    protected static ?string $navigationLabel = 'Sales Targets';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string | \UnitEnum | null $navigationGroup = 'Sales Oversight';

    protected static ?int $navigationSort = 11;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['contract', 'leader', 'marketer'])->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('contract.project_name')->label('Project')->searchable()->placeholder('-'),
                TextColumn::make('leader.name')->label('Leader')->placeholder('-'),
                TextColumn::make('marketer.name')->label('Marketer')->placeholder('-'),
                TextColumn::make('must_sell_units_count')
                    ->label('Units Goal')
                    ->numeric()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('assigned_target_value')
                    ->label('Target Value')
                    ->state(fn (SalesTarget $record): string => $record->assigned_target_value !== null ? number_format((float) $record->assigned_target_value, 2) . ' AED' : '-')
                    ->sortable(),
                TextColumn::make('target_type')->label('Target Type')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('start_date')->date(),
                TextColumn::make('end_date')->date(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(GovernanceStatusCatalog::salesTargetStatusOptions()),
            ])
            ->actions([
                Action::make('updateTargetStatus')
                    ->label(__('filament-admin.resources.sales_targets.actions.set_status'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (SalesTarget $record): bool => static::canGovernanceMutation('sales.targets.update'))
                    ->schema([
                        Select::make('status')
                            ->label(__('filament-admin.resources.sales_targets.fields.status'))
                            ->required()
                            ->options(GovernanceStatusCatalog::salesTargetStatusOptions())
                            ->default(fn (SalesTarget $record): string => $record->status),
                    ])
                    ->action(function (SalesTarget $record, array $data): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $newStatus = (string) $data['status'];

                        if ($record->status === $newStatus) {
                            return;
                        }

                        app(SalesTargetService::class)->governanceUpdateTargetStatus($record->id, $newStatus, $actor);

                        Notification::make()
                            ->success()
                            ->title(__('filament-admin.resources.sales_targets.notifications.status_updated'))
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesTargets::route('/'),
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
