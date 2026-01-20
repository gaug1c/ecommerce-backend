<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminOrderController extends Controller
{
    /*
     |--------------------------------------------------------------------------
     | LISTE DE TOUTES LES COMMANDES
     |--------------------------------------------------------------------------
     */
    public function index(Request $request)
    {
        $query = Order::with(['user', 'items.product', 'payment']);

        // Filtres
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $orders = $query
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Liste des commandes',
            'data' => $orders,
            'currency' => 'FCFA'
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | DETAILS D’UNE COMMANDE
     |--------------------------------------------------------------------------
     */
    public function show($id)
    {
        $order = Order::with([
            'user',
            'items.product.category',
            'payment'
        ])->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détails de la commande',
            'data' => $order,
            'currency' => 'FCFA'
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | ANNULATION FORCÉE PAR L’ADMIN
     |--------------------------------------------------------------------------
     */
    public function cancel(Request $request, $id)
    {
        $order = Order::with('items.product')->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        if (in_array($order->status, ['delivered', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande ne peut plus être annulée'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Restaurer le stock
            foreach ($order->items as $item) {
                $item->product->increment('stock', $item->quantity);
            }

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $request->reason ?? 'Annulation forcée par l’administrateur'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande annulée par l’administrateur',
                'data' => $order
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l’annulation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
     |--------------------------------------------------------------------------
     | FORCER LA FINALISATION D’UNE COMMANDE
     |--------------------------------------------------------------------------
     */
    public function forceComplete(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        if ($order->status === 'delivered') {
            return response()->json([
                'success' => false,
                'message' => 'Commande déjà livrée'
            ], 422);
        }

        $order->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commande marquée comme livrée par l’administration',
            'data' => $order
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | CHANGER LE STATUT D’UNE COMMANDE (ADMIN)
     |--------------------------------------------------------------------------
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        $order->update([
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Statut de la commande mis à jour',
            'data' => $order
        ]);
    }
}
