<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class FacebookAuthController extends Controller
{
    /**
     * Login / Register via Facebook OAuth (Web)
     */
    public function login(Request $request)
    {
        // 1️⃣ Validation
        $request->validate([
            'access_token' => 'required|string',
        ]);

        // 2️⃣ Vérification du token Facebook
        try {
            $fbUser = Socialite::driver('facebook')
                ->stateless()
                ->userFromToken($request->access_token);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Facebook token'
            ], 401);
        }

        // 3️⃣ Sécurité email vérifié (Facebook renvoie toujours email si scope 'email' demandé)
        if (empty($fbUser->getEmail())) {
            return response()->json([
                'success' => false,
                'message' => 'Facebook email not available'
            ], 403);
        }

        // 4️⃣ Transaction DB (robuste)
        $user = DB::transaction(function () use ($fbUser) {

            // 🔍 Recherche par provider
            $user = User::where('provider', 'facebook')
                ->where('provider_id', $fbUser->getId())
                ->first();

            if ($user) {
                return $user;
            }

            // 🔁 Lier un compte existant par email
            $user = User::where('email', $fbUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'provider'    => 'facebook',
                    'provider_id' => $fbUser->getId(),
                    'email_verified_at' => now(),
                ]);
                return $user;
            }

            // 🆕 Création utilisateur
            $user = User::create([
                'name'              => $fbUser->getName(),
                'email'             => $fbUser->getEmail(),
                'provider'          => 'facebook',
                'provider_id'       => $fbUser->getId(),
                'email_verified_at' => now(),
                'password'          => null,
                'is_active'         => true,
            ]);

            // 🎭 Rôle par défaut
            $user->roles()->syncWithoutDetaching([
                Role::where('name', 'customer')->firstOrFail()->id
            ]);

            return $user;
        });

        // 5️⃣ Compte désactivé
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account disabled'
            ], 403);
        }

        // 6️⃣ Token API (Sanctum)
        $token = $user->createToken('auth_token')->plainTextToken;

        // 7️⃣ Réponse
        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user->load('roles'),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }
}
