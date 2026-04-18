<?php

namespace App\Services\registartion;

use App\Models\User;
use App\Services\Auth\AuthenticatedUserPayloadService;
use Illuminate\Support\Facades\Hash;

class login
{
    public function __construct(
        protected AuthenticatedUserPayloadService $payloads,
    ) {}

    /**
     * Attempt to authenticate a user and return a token with safe profile data.
     *
     * @param array $data
     * @return array
     */
    public function attemptLogin(array $data): array
    {
        $user = User::where('email', $data['email'])
            ->whereNull('deleted_at')
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return [
                'status' => 401,
                'message' => 'Invalid Credentials',
            ];
        }

        if (!$user->is_active) {
            return [
                'status' => 403,
                'message' => 'Account is deactivated. Contact your administrator.',
            ];
        }

        $token = $user->createToken($user->id . '-AuthToken')->plainTextToken;

        return [
            'status'  => 200,
            'token'   => $token,
            ...$this->payloads->contract($user),
        ];
    }
}
