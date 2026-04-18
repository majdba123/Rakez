<?php

namespace App\Filament\Admin\Resources\ClaimFiles\Pages\Concerns;

use App\Models\SalesReservation;
use App\Models\User;
use App\Services\Credit\ClaimFileService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

trait HasClaimFileGenerationHeaderActions
{
    /**
     * @return array<int, \Filament\Actions\Action>
     */
    protected function claimFileGenerationHeaderActions(): array
    {
        /** @var class-string $resourceClass */
        $resourceClass = static::$resource;

        return [
            Action::make('generateBulk')
                ->label(__('filament-admin.resources.claim_files.actions.generate_bulk'))
                ->icon('heroicon-o-folder-plus')
                ->visible(fn (): bool => $resourceClass::canCreate())
                ->form([
                    Select::make('reservation_ids')
                        ->label(__('filament-admin.resources.claim_files.fields.sold_bookings'))
                        ->required()
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => static::claimCandidateOptions()),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();

                    abort_unless($actor instanceof User, 403);

                    app(ClaimFileService::class)->generateClaimFilesBulk($data['reservation_ids'], $actor);

                    Notification::make()
                        ->success()
                        ->title(__('filament-admin.resources.claim_files.notifications.bulk_generated'))
                        ->send();
                }),
            Action::make('generateCombined')
                ->label(__('filament-admin.resources.claim_files.actions.generate_combined'))
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn (): bool => $resourceClass::canCreate())
                ->form([
                    Select::make('booking_ids')
                        ->label(__('filament-admin.resources.claim_files.fields.sold_bookings'))
                        ->required()
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => static::claimCandidateOptions()),
                    Select::make('claim_type')
                        ->required()
                        ->default('commission')
                        ->options([
                            'commission' => __('filament-admin.resources.claim_files.fields.claim_type_commission'),
                        ]),
                    Textarea::make('notes')
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();

                    abort_unless($actor instanceof User, 403);

                    app(ClaimFileService::class)->generateCombinedClaimFile(
                        $data['booking_ids'],
                        (string) $data['claim_type'],
                        $data['notes'] ?? null,
                        $actor
                    );

                    Notification::make()
                        ->success()
                        ->title(__('filament-admin.resources.claim_files.notifications.combined_generated'))
                        ->send();
                }),
        ];
    }

    protected static function claimCandidateOptions(): array
    {
        return SalesReservation::query()
            ->with(['contract', 'contractUnit'])
            ->where('credit_status', 'sold')
            ->whereDoesntHave('claimFile')
            ->whereNotIn('id', function ($query) {
                $query->select('sales_reservation_id')->from('claim_file_reservations');
            })
            ->orderByDesc('confirmed_at')
            ->get()
            ->mapWithKeys(fn (SalesReservation $reservation): array => [
                $reservation->id => sprintf(
                    '#%d %s / %s / %s',
                    $reservation->id,
                    $reservation->contract?->project_name ?? '-',
                    $reservation->contractUnit?->unit_number ?? '-',
                    $reservation->client_name ?? '-'
                ),
            ])
            ->all();
    }
}
