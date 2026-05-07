<?php

namespace App\Http\Requests\Sales;

use App\Models\SalesReservation;
use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Sales\Concerns\ValidatesReservationParticipants;

class SyncReservationParticipantsRequest extends FormRequest
{
    use ValidatesReservationParticipants;

    public function authorize(): bool
    {
        $reservation = $this->route('reservation');
        if (!$reservation instanceof SalesReservation) {
            return false;
        }

        return $this->user()->can('manageParticipants', $reservation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::syncParticipantsRules();
    }
}
