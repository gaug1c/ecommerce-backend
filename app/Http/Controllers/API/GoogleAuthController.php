<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Login / Register via Google OAuth (Web)
     */
    public function login(Request $request)
    {
        // 1️⃣ Validation
        $request->validate([
            'access_token' => 'required|string',
        ]);

        $tokenInfo = file_get_contents("https://www.googleapis.com/oauth2/v1/tokeninfo?access_token={$request->access_token}");
        $tokenInfo = json_decode($tokenInfo, true);

        if (isset($tokenInfo['error'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Google token'
            ], 401);
        }

        // token valide, récupérer infos utilisateur
        $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->access_token);


        // 3️⃣ Sécurité email vérifié
        if (!$googleUser->user['email_verified']) {
            return response()->json([
                'success' => false,
                'message' => 'Google email not verified'
            ], 403);
        }

        // 4️⃣ Transaction DB (robuste)
        $user = DB::transaction(function () use ($googleUser) {

            // 🔍 Recherche par provider
            $user = User::where('provider', 'google')
                ->where('provider_id', $googleUser->getId())
                ->first();

            if ($user) {
                return $user;
            }

            // 🔁 Lier un compte existant par email
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'provider'    => 'google',
                    'provider_id' => $googleUser->getId(),
                    'email_verified_at' => now(),
                ]);
                return $user;
            }

            // 🆕 Création utilisateur
            $user = User::create([
                'name'              => $googleUser->getName(),
                'email'             => $googleUser->getEmail(),
                'provider'          => 'google',
                'provider_id'       => $googleUser->getId(),
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
