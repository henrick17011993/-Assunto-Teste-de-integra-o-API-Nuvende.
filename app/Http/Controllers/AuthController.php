<?php

namespace App\Http\Controllers;

use App\Services\NuvendeAuthService;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(NuvendeAuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login()
    {
        try {
            $token = $this->authService->getToken();
            return response()->json([
                'success' => true,
                'access_token' => $token
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}