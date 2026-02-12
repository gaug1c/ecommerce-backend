<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SellerProfile;
use App\Models\Order;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * ADMIN DASHBOARD
     */
    public function index(Request $request)
    {
        $admin = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Admin dashboard data',
            'data' => [
                'admin' => [
                    'id'    => $admin->id,
                    'name'  => $admin->name,
                    'email' => $admin->email,
                ],

                'stats' => [
                    'total_users'    => User::count(),
                    'total_sellers'  => User::whereHas('roles', fn ($q) => $q->where('name', 'seller'))->count(),
                    'total_customers'=> User::whereHas('roles', fn ($q) => $q->where('name', 'customer'))->count(),

                    'pending_sellers'=> SellerProfile::where('seller_status', 'pending')->count(),

                    // si tu as une table orders
                    'total_orders'   => class_exists(Order::class) ? Order::count() : 0,
                ],

                'latest_users' => User::latest()
                    ->limit(5)
                    ->get(['id', 'name', 'email', 'created_at']),

                'latest_sellers' => SellerProfile::with('user')
                    ->latest()
                    ->limit(5)
                    ->get(),
            ]
        ]);
    }
}
