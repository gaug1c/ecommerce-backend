<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\User;
use App\Notifications\SellerApprovedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /*
     |--------------------------------------------------------------------------
     | LISTE DES UTILISATEURS
     |--------------------------------------------------------------------------
     */
    public function index(Request $request)
    {
        $users = User::with(['roles', 'sellerProfile'])
            ->when($request->role, function ($q) use ($request) {
                $q->whereHas('roles', fn ($r) =>
                    $r->where('name', $request->role)
                );
            })
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | DETAILS UTILISATEUR
     |--------------------------------------------------------------------------
     */
    public function show($id)
    {
        $user = User::with(['roles', 'sellerProfile'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | CREER UTILISATEUR (ADMIN)
     |--------------------------------------------------------------------------
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'phone'    => 'nullable|string',
            'role'     => 'required|in:customer,seller,admin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'password' => Hash::make($request->password),
            'is_active'=> true
        ]);

        $role = Role::where('name', $request->role)->firstOrFail();
        $user->roles()->sync([$role->id]);

        // Création profil vendeur si nécessaire
        if ($request->role === 'seller') {
            SellerProfile::create([
                'user_id'       => $user->id,
                'shop_name'     => 'Boutique de '.$user->name,
                'seller_status' => 'active',
                'id_card_status'=> 'verified',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès',
            'data' => $user->load('roles', 'sellerProfile')
        ], 201);
    }

    /*
     |--------------------------------------------------------------------------
     | METTRE A JOUR UTILISATEUR
     |--------------------------------------------------------------------------
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'     => 'sometimes|string|max:255',
            'email'    => "sometimes|email|unique:users,email,$id",
            'phone'    => 'nullable|string',
            'password' => 'nullable|string|min:8',
            'role'     => 'sometimes|in:customer,seller,admin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($request->only('name', 'email', 'phone'));

        if ($request->password) {
            $user->update([
                'password' => Hash::make($request->password)
            ]);
        }

        if ($request->role) {
            $role = Role::where('name', $request->role)->firstOrFail();
            $user->roles()->sync([$role->id]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur mis à jour',
            'data' => $user->load('roles', 'sellerProfile')
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | SUPPRIMER UTILISATEUR
     |--------------------------------------------------------------------------
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Soft delete recommandé
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé'
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | ACTIVER COMPTE
     |--------------------------------------------------------------------------
     */
    public function activate($id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Compte activé'
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | DESACTIVER COMPTE
     |--------------------------------------------------------------------------
     */
    public function deactivate($id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => false]);

        // Optionnel : supprimer tokens actifs
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Compte désactivé'
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | LISTE DES VENDEURS
     |--------------------------------------------------------------------------
     */
    public function sellers()
    {
        $sellers = User::with(['roles', 'sellerProfile'])
            ->whereHas('roles', fn ($q) => $q->where('name', 'seller'))
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $sellers
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | APPROUVER VENDEUR
     |--------------------------------------------------------------------------
     */
    public function approveSeller($sellerProfileId)
    {
        $sellerProfile = SellerProfile::with('user')->findOrFail($sellerProfileId);

        $sellerProfile->update([
            'seller_status'  => 'active',
            'id_card_status' => 'verified',
        ]);

        // 🔔 ENVOI NOTIFICATION AU VENDEUR
        $sellerProfile->user->notify(
            new SellerApprovedNotification($sellerProfile)
        );

        return response()->json([
            'success' => true,
            'message' => 'Vendeur vérifié',
            'data' => $sellerProfile->load('user')
        ]);
    }


    /*
     |--------------------------------------------------------------------------
     | SUSPENDRE VENDEUR
     |--------------------------------------------------------------------------
     */
    public function suspendSeller($sellerProfileId)
    {
        $sellerProfile = SellerProfile::with('user')->findOrFail($sellerProfileId);

        $sellerProfile->update([
            'seller_status' => 'blocked',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vendeur suspendu'
        ]);
    }

}
