<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    /**
     * Handle user registration.
     *
     * Creates a new user and associated wallet atomically,
     * then returns the user data with a JWT token.
     */
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'cpf' => $validated['cpf'],
                'password' => Hash::make($validated['password']),
            ]);

            $user->wallet()->create([
                'balance' => 0,
            ]);

            return $user;
        });

        $token = auth()->login($user);

        return response()->json([
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'cpf' => $user->cpf,
            ],
            'token' => $token,
        ], 201);
    }
}
