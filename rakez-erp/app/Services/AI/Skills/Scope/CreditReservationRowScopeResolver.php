<?php

namespace App\Services\AI\Skills\Scope;

use App\Models\CreditFinancingTracker;
use App\Models\SalesReservation;
use App\Models\User;
use App\Services\AI\Skills\Scope\Contracts\RowScopeResolverContract;

class CreditReservationRowScopeResolver implements RowScopeResolverContract
{
    public function resolve(User $user, array $definition, array $input): array
    {
        $rowScope = (array) ($definition['row_scope'] ?? []);
        $idField = (string) ($rowScope['id_field'] ?? 'reservation_id');
        $reservationId = isset($input[$idField]) ? (int) $input[$idField] : 0;
        if ($reservationId < 1) {
            return [
                'status' => 'needs_input',
                'message' => "This skill requires an explicit `{$idField}` before execution.",
                'reason' => 'row_scope.credit_reservation_id_required',
                'follow_up_questions' => ["Provide `{$idField}` to continue."],
                'data' => [
                    'missing_fields' => [$idField],
                ],
            ];
        }

        $reservation = SalesReservation::query()->find($reservationId);
        if (! $reservation) {
            return [
                'status' => 'not_found',
                'message' => 'The requested reservation could not be found within your accessible scope.',
                'reason' => 'row_scope.credit_reservation_not_found',
                'data' => [
                    'reservation_id' => $reservationId,
                ],
            ];
        }

        $trackerExists = CreditFinancingTracker::query()
            ->where('sales_reservation_id', $reservationId)
            ->exists();

        if (! $trackerExists) {
            return [
                'status' => 'not_found',
                'message' => 'No financing tracker exists for the requested reservation.',
                'reason' => 'row_scope.credit_tracker_not_found',
                'data' => [
                    'reservation_id' => $reservationId,
                ],
            ];
        }

        return [
            'status' => 'ok',
            'normalized_input' => $input,
            'data' => [
                'record_type' => 'credit_financing_tracker',
                'record_id' => $reservationId,
            ],
        ];
    }
}
