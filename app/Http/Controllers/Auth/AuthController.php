<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;

class AuthController extends Controller
{
    /**
     * Handle user login.
     *
     * Attempts authentication with provided credentials.
     * Returns a JWT token on success, or a generic 401 error on failure
     * without revealing which field is incorrect.
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only(['email', 'password']);

        $token = auth()->attempt($credentials);

        if (!$token) {
            return response()->json([
                'error' => 'Credenciais inválidas',
            ], 401);
        }

        return response()->json([
            'token' => $token,
        ]);
    }

    /**
     * Handle user logout.
     *
     * Invalidates the current JWT token.
     */
    public function logout()
    {
        auth()->logout();

        return response()->json([
            'message' => 'Logout realizado com sucesso',
        ]);
    }
}
