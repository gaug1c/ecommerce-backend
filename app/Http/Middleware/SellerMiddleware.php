<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SellerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié'
            ], 401);
        }

        if (!$request->user()->hasRole('seller')) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Droits vendeur requis.'
            ], 403);
        }

        return $next($request);
    }
}
