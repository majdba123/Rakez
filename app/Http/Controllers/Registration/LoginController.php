<?php

namespace App\Http\Controllers\Registration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\LoginRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Registration\LoginService;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    /**
     * Display a listing of the resource.
     */
        /**
     * Handle the registration of a new user.
     *
     * @param LoginRequest   $request
     * @return JsonResponse
     */

    protected LoginService $loginService;

    public function __construct(LoginService $loginService)
    {
        $this->loginService = $loginService;
    }


    public function login(LoginRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $result = $this->loginService->attemptLogin($validatedData);

        if ($result['status'] !== 200) {
            return ApiResponse::error($result['message'], $result['status']);
        }

        return ApiResponse::success([
            'access_token' => $result['token'],
            'token' => $result['token'],
            'user' => $result['user'],
        ]);
    }

    public function logout(): JsonResponse
    {
        if (auth()->user()) {
            auth()->user()->tokens()->delete();
        }

        return ApiResponse::success(null, 'logged out');
    }

}
