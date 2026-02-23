<?php

namespace App\Services\registartion;

use App\Models\User;
use App\Models\Driver;
use App\Models\Provider_Product; // Import your ProviderProduct model
use App\Models\Provider_Service; // Import your ProviderService model
use Illuminate\Support\Facades\Hash;
class login
{
    /**
     * Register a new user and create related records based on user type.
     *
     * @param array $data
     * @return User
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
