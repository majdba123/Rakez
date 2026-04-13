<?php

namespace App\Services\AI\Skills\Scope;

use App\Models\SalesReservation;
use App\Models\User;
use App\Services\AI\Skills\Scope\Contracts\RowScopeResolverContract;

class SalesReservationRowScopeResolver implements RowScopeResolverContract
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
                'reason' => 'row_scope.reservation_id_required',
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
                'reason' => 'row_scope.reservation_not_found',
                'data' => [
                    'reservation_id' => $reservationId,
                ],
            ];
        }

        if (! $user->can('view', $reservation)) {
            return [
                'status' => 'denied',
                'message' => 'The requested reservation is outside your accessible scope.',
                'reason' => 'row_scope.reservation_forbidden',
                'data' => [
                    'reservation_id' => $reservationId,
                ],
            ];
        }

        return [
            'status' => 'ok',
            'normalized_input' => $input,
            'data' => [
                'record_type' => 'sales_reservation',
                'record_id' => $reservationId,
            ],
        ];
    }
}
