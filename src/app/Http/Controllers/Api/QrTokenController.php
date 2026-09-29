<?php

namespace App\Http\Controllers\Api;

use App\Services\Qr\QrTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrTokenController
{
    public function show(Request $request, QrTokenService $qr): JsonResponse
    {
        $user = $request->user();

        // Se revisa en cada emisión: un usuario desactivado deja de recibir QR
        if (! $user->is_active) {
            return response()->json(['message' => 'Usuario inactivo.'], 403);
        }

        return response()->json($qr->issue($user));
    }
}
