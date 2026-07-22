<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned the "api" middleware group.
|
*/

// Public routes
Route::post('/register', [\App\Http\Controllers\Auth\RegisterController::class, 'register']);
Route::post('/login', [\App\Http\Controllers\Auth\AuthController::class, 'login'])
    ->middleware('throttle:login');

// Protected routes
Route::middleware('jwt.auth')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\Auth\AuthController::class, 'logout']);

    // List users for transfer recipient selection (excludes current user)
    Route::get('/users', function () {
        $currentUserId = auth()->id();
        $users = \App\Models\User::where('id', '!=', $currentUserId)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();
        return response()->json($users);
    });

    // Wallet operations (rate limited: 60 req/min)
    Route::prefix('wallet')->middleware('throttle:financial')->group(function () {
        Route::get('/balance', [\App\Http\Controllers\Wallet\WalletController::class, 'balance']);
        Route::get('/statement', [\App\Http\Controllers\Wallet\WalletController::class, 'statement']);
        Route::post('/deposit', [\App\Http\Controllers\Wallet\WalletController::class, 'deposit']);
        Route::post('/transfer', [\App\Http\Controllers\Wallet\TransferController::class, 'transfer']);
        Route::post('/reverse/{transaction_id}', [\App\Http\Controllers\Wallet\ReversalController::class, 'reverse'])
            ->whereNumber('transaction_id');
    });
});
