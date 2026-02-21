<?php

namespace App\Services\Registration;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class login
{
    /**
     * Attempt login with email and password.
     *
     * @param array $data Must contain 'email' and 'password'
     * @return array{status: int, message?: string, token?: string, user?: User}
     */
    public function attemptLogin(array $data): array
    {
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return [
                'status' => 401,
                'message' => 'Invalid Credentials'
            ];
        }

        $token = $user->createToken($user->id . '-AuthToken')->plainTextToken;

        return [
            'status' => 200,
            'token' => $token,
            'user' => $user
        ];
    }
}
