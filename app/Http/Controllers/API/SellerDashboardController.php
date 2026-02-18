<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class SellerDashboardController extends Controller
{
    /**
     * Statistiques principales du vendeur
     */
    public function stats()
    {
        $user = Auth::user();

        // Vérifie que le produit a bien un seller_id
        $totalProducts = Product::where('seller_id', $user->id)->count();

        // Récupère les commandes contenant les produits de ce vendeur
        $ordersQuery = Order::whereHas('items.product', function ($q) use ($user) {
            $q->where('seller_id', $user->id);
        });

        $orders = $ordersQuery->get();

        // Total produits vendus
        $productsSold = $orders->sum(function ($order) use ($user) {
            return $order->items
                ->filter(fn($item) => $item->product && $item->product->seller_id === $user->id)
                ->sum('quantity');
        });

        // Nombre total de commandes pour ce vendeur
        $totalSales = $orders->count();

        // Chiffre d'affaires total pour ce vendeur
        $revenue = $orders->sum(function ($order) use ($user) {
            return $order->items
                ->filter(fn($item) => $item->product && $item->product->seller_id === $user->id)
                ->sum(fn($item) => $item->quantity * $item->price);
        });

        return response()->json([
            'totalProducts' => $totalProducts,
            'productsSold'  => $productsSold,
            'totalSales'    => $totalSales,
            'revenue'       => $revenue,
        ]);
    }

    /**
     * Récupérer les dernières commandes du vendeur
     */
    public function recentOrders(Request $request)
    {
        $user = Auth::user();
        $limit = $request->query('limit', 10);

        $orders = Order::with(['items.product', 'customer'])
            ->whereHas('items.product', function ($q) use ($user) {
                $q->where('seller_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($order) use ($user) {
                // Ne garder que les items du vendeur
                $order->items = $order->items->filter(fn($item) => $item->product && $item->product->seller_id === $user->id);
                return $order;
            });

        return response()->json($orders);
    }
}
