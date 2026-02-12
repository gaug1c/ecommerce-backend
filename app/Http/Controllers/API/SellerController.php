<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\API\Admin\UserController;
use App\Notifications\SellerRejectedNotification;
use App\Notifications\NewSellerRequestNotification;
use Illuminate\Support\Facades\Validator;

class SellerController extends Controller
{
    public function becomeSeller(Request $request)
    {
        $user = $request->user();

        /* =======================
            DÉJÀ VENDEUR ?
        ======================== */
        if ($user->isSeller()) {
            return response()->json([
                'success' => false,
                'message' => 'User is already a seller'
            ], 409);
        }

        /* =======================
            VALIDATION
        ======================== */
        $validator = Validator::make($request->all(), [
            'shop_name'        => 'required|string|unique:seller_profiles',
            'shop_address'     => 'nullable|string',
            'shop_city'        => 'nullable|string',
            'shop_country'     => 'nullable|string',
            'shop_postal_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        /* =======================
            CRÉATION PROFIL VENDEUR
        ======================== */
        $sellerProfile = SellerProfile::create([
            'user_id'          => $user->id,
            'shop_name'        => $request->shop_name,
            'shop_address'     => $request->shop_address,
            'shop_city'        => $request->shop_city,
            'shop_country'     => $request->shop_country,
            'shop_postal_code' => $request->shop_postal_code,
            'seller_status'    => 'pending',
            'id_card_status'   => 'pending',
        ]);

        // récupérer les admins
        $admins = User::whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->get();

        // envoyer notification
        foreach ($admins as $admin) {
            $admin->notify(new NewSellerRequestNotification($sellerProfile));
        }

        /* =======================
            AJOUT RÔLE SELLER
        ======================== */
        $sellerRole = Role::where('name', 'seller')->firstOrFail();
        $user->roles()->syncWithoutDetaching($sellerRole->id);

        return response()->json([
            'success' => true,
            'message' => 'Seller request submitted successfully',
            'data' => $user->load('roles', 'sellerProfile')
        ], 201);
    }

    public function rejectSeller(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string'
        ]);

        $sellerProfile = SellerProfile::findOrFail($id);

        $sellerProfile->update([
            'seller_status' => 'rejected',
            'rejection_reason' => $request->reason
        ]);

        // notifier l'utilisateur
        $sellerProfile->user->notify(
            new SellerRejectedNotification($sellerProfile, $request->reason)
        );

        return response()->json([
            'success' => true,
            'message' => 'Seller request rejected successfully'
        ]);
    }

}
