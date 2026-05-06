<?php

namespace App\Http\Requests\Sales\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesReservationParticipants
{
    /**
     * @return array<string, mixed>
     */
    protected static function reservationParticipantNestedRules(string $participantItemsPrefix): array
    {
        return [
            "{$participantItemsPrefix}.user_id" => ['required', 'distinct', Rule::exists('users', 'id')],
            "{$participantItemsPrefix}.did_bring" => 'sometimes|boolean',
            "{$participantItemsPrefix}.did_convince" => 'sometimes|boolean',
            "{$participantItemsPrefix}.did_close" => 'sometimes|boolean',
            "{$participantItemsPrefix}.weight" => 'nullable|numeric|min:0',
            "{$participantItemsPrefix}.notes" => 'nullable|string',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function storeParticipantsRules(): array
    {
        return array_merge([
            'participants' => 'nullable|array',
        ], self::reservationParticipantNestedRules('participants.*'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function syncParticipantsRules(): array
    {
        return array_merge([
            'participants' => 'sometimes|nullable|array',
        ], self::reservationParticipantNestedRules('participants.*'));
    }
}
