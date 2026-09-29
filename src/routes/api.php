<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\QrTokenController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/me', fn (Request $request) => [
        'id' => $request->user()->id,
        'name' => $request->user()->name,
        'role' => $request->user()->role->value,
    ]);

    Route::get('/qr-token', [QrTokenController::class, 'show'])->middleware('throttle:30,1');
});
