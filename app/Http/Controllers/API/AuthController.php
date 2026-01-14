<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * REGISTER
     * Crée un utilisateur client ou vendeur
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|max:255|unique:users',
            'password'              => 'required|string|min:8|confirmed',
            'phone'                 => 'nullable|string',
            
            // vendeur (optionnel)
            'role'                  => 'nullable|in:customer,seller',
            'shop_name'             => 'required_if:role,seller|unique:seller_profiles',
            'shop_address'          => 'nullable|string|max:255',
            'shop_city'             => 'required_if:role,seller|string|max:255',
            'shop_country'          => 'nullable|string|max:255',
            'shop_postal_code'      => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        // =======================
        // CREATE USER
        // =======================
        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // =======================
        // ATTACH ROLE
        // =======================
        $roleName = $request->role ?? 'customer';
        $role = Role::where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role->id);

        // =======================
        // CREATE SELLER PROFILE
        // =======================
        if ($roleName === 'seller') {
            SellerProfile::create([
                'user_id'          => $user->id,
                'shop_name'        => $request->shop_name,
                'shop_address'     => $request->shop_address,
                'shop_city'        => $request->shop_city,
                'shop_country'     => $request->shop_country ?? 'Gabon',
                'shop_postal_code' => $request->shop_postal_code,
                'seller_status'    => 'pending',
                'id_card_status'   => 'pending',
            ]);
        }

        // =======================
        // CREATE TOKEN
        // =======================
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data'    => [
                'user'         => $user->load('roles', 'sellerProfile'),
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ]
        ], 201);
    }

    /**
     * LOGIN
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data'    => [
                'user'         => $user->load('roles', 'sellerProfile'),
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ]
        ]);
    }

    /**
     * LOGOUT
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * CURRENT USER
     */
    public function user(Request $request)
    {
        return response()->json([
            'success' => true,
            'data'    => $request->user()->load('roles', 'sellerProfile')
        ]);
    }
}
