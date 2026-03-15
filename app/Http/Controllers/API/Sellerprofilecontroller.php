<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Gère la lecture et la mise à jour du profil vendeur.
 *
 * Routes à ajouter dans api.php (dans le groupe v1/seller) :
 *   Route::get('/profile',  [SellerProfileController::class, 'show']);
 *   Route::post('/profile', [SellerProfileController::class, 'update']);
 *   Route::put('/profile',  [SellerProfileController::class, 'update']);
 */
class SellerProfileController extends Controller
{
    /**
     * GET /api/v1/seller/profile
     * Retourne le profil vendeur de l'utilisateur authentifié.
     */
    public function show(Request $request)
    {
        $user = $request->user()->load('sellerProfile', 'roles');

        return response()->json([
            'success' => true,
            'data'    => [
                'user' => $user,
            ],
        ]);
    }

    /**
     * POST /api/v1/seller/profile  (ou PUT)
     * Met à jour les informations de la boutique du vendeur.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'shop_name'        => 'sometimes|string|max:255',
            'shop_description' => 'nullable|string|max:2000',
            'shop_city'        => 'nullable|string|max:100',
            'shop_country'     => 'nullable|string|max:100',
            'shop_address'     => 'nullable|string|max:500',
            'shop_phone'       => 'nullable|string|max:30',
            'whatsapp'         => 'nullable|string|max:100',
            'facebook'         => 'nullable|url|max:255',
            'instagram'        => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $sellerProfile = $user->sellerProfile;

        if (! $sellerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Profil vendeur introuvable.',
            ], 404);
        }

        $sellerProfile->update($request->only([
            'shop_name',
            'shop_description',
            'shop_city',
            'shop_country',
            'shop_address',
            'shop_phone',
            'whatsapp',
            'facebook',
            'instagram',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès.',
            'data'    => [
                'user' => $user->fresh()->load('sellerProfile', 'roles'),
            ],
        ]);
    }
}