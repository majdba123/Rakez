<?php

namespace App\Filament\Admin\Resources\ClaimFiles\Pages;

use App\Filament\Admin\Resources\ClaimFiles\ClaimFileResource;
use App\Models\SalesReservation;
use App\Models\User;
use App\Services\Credit\ClaimFileService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListClaimFiles extends ListRecords
{
    protected static string $resource = ClaimFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateBulk')
                ->label('Generate Bulk')
                ->icon('heroicon-o-folder-plus')
                ->visible(fn (): bool => ClaimFileResource::canCreate())
                ->form([
                    Select::make('reservation_ids')
                        ->label('Sold Bookings')
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
                        ->title('Bulk claim file generation completed.')
                        ->send();
                }),
            Action::make('generateCombined')
                ->label('Generate Combined')
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn (): bool => ClaimFileResource::canCreate())
                ->form([
                    Select::make('booking_ids')
                        ->label('Sold Bookings')
                        ->required()
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => static::claimCandidateOptions()),
                    Select::make('claim_type')
                        ->required()
                        ->default('commission')
                        ->options([
                            'commission' => 'Commission',
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
                        ->title('Combined claim file generated.')
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
