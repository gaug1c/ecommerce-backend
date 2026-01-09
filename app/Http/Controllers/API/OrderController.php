<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Afficher l'historique des commandes
     * Adapté pour l'e-commerce au Gabon
     */
    public function index(Request $request)
    {
        $query = Order::with('items.product')
            ->where('user_id', $request->user()->id);

        // Filtrer par statut
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filtrer par période
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Historique des commandes',
            'data' => $orders,
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Afficher les détails d'une commande
     */
    public function show(Request $request, $id)
    {
        $order = Order::with('items.product.category', 'payment')
            ->where('user_id', $request->user()->id)
            ->find($id);

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

    /**
     * Créer une nouvelle commande à partir du panier
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipping_address' => 'required|string',
            'shipping_city' => 'required|string|in:Libreville,Port-Gentil,Franceville,Oyem,Moanda,Mouila,Lambaréné,Tchibanga,Koulamoutou,Makokou',
            'shipping_postal_code' => 'nullable|string',
            'shipping_country' => 'required|string',
            'phone' => [
                'required',
                'string',
                'regex:/^(\+241|00241)?[0-9]{8,9}$/'
            ],
            'delivery_instructions' => 'nullable|string',
            'payment_method' => 'required|in:card,mobile_money,bank_transfer,cash_on_delivery'
        ], [
            'shipping_address.required' => 'L\'adresse de livraison est obligatoire',
            'shipping_city.required' => 'La ville de livraison est obligatoire',
            'shipping_city.in' => 'Cette ville n\'est pas dans notre zone de livraison',
            'shipping_country.required' => 'Le pays est obligatoire',
            'phone.required' => 'Le numéro de téléphone est obligatoire',
            'phone.regex' => 'Le format du numéro de téléphone est invalide (ex: +24177123456 ou 07123456)',
            'payment_method.required' => 'Le mode de paiement est obligatoire',
            'payment_method.in' => 'Ce mode de paiement n\'est pas accepté'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $cart = Cart::with('items.product')->where('user_id', $request->user()->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Votre panier est vide'
            ], 422);
        }

        // Vérifier la disponibilité du stock
        foreach ($cart->items as $item) {
            if ($item->product->stock < $item->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Stock insuffisant pour le produit: {$item->product->name}. Stock disponible: {$item->product->stock}"
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            // Calculer les montants
            $subtotal = 0;
            $shippingCost = 0;
            
            foreach ($cart->items as $item) {
                $price = $item->product->discount_price ?? $item->product->price;
                $subtotal += $item->quantity * $price;
                
                // Frais de livraison
                if ($item->product->shipping_cost) {
                    $shippingCost += $item->product->shipping_cost;
                }
            }

            // Frais de livraison selon la ville
            $cityShippingCosts = [
                'Libreville' => 2000,
                'Port-Gentil' => 5000,
                'Franceville' => 7000,
                'Oyem' => 6000,
                'Moanda' => 7000,
                'Mouila' => 5000,
                'Lambaréné' => 4000,
                'Tchibanga' => 6000,
                'Koulamoutou' => 6000,
                'Makokou' => 7000
            ];

            $baseShippingCost = $cityShippingCosts[$request->shipping_city] ?? 5000;
            $totalShippingCost = $shippingCost + $baseShippingCost;
            $totalAmount = $subtotal + $totalShippingCost;

            // Créer la commande
            $order = Order::create([
                'user_id' => $request->user()->id,
                'order_number' => 'CMD-' . strtoupper(uniqid()),
                'subtotal' => $subtotal,
                'shipping_cost' => $totalShippingCost,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'payment_status' => 'pending',
                'payment_method' => $request->payment_method,
                'shipping_address' => $request->shipping_address,
                'shipping_city' => $request->shipping_city,
                'shipping_postal_code' => $request->shipping_postal_code,
                'shipping_country' => $request->shipping_country ?? 'Gabon',
                'phone' => $request->phone,
                'delivery_instructions' => $request->delivery_instructions
            ]);

            // Créer les items de commande et mettre à jour le stock
            foreach ($cart->items as $item) {
                $price = $item->product->discount_price ?? $item->product->price;
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $price,
                    'subtotal' => $item->quantity * $price
                ]);

                // Décrémenter le stock
                $item->product->decrement('stock', $item->quantity);
            }

            // Vider le panier
            $cart->items()->delete();

            DB::commit();

            $order->load('items.product');

            return response()->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'data' => $order,
                'currency' => 'FCFA',
                'next_step' => 'Procéder au paiement'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Annuler une commande
     */
    public function cancel(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        // Vérifier si la commande peut être annulée
        if (!in_array($order->status, ['pending', 'processing', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette commande ne peut plus être annulée. Statut actuel: ' . $order->status
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Restaurer le stock des produits
            foreach ($order->items as $item) {
                $item->product->increment('stock', $item->quantity);
            }

            // Mettre à jour le statut
            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $request->reason ?? 'Annulé par le client'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande annulée avec succès',
                'data' => $order
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation de la commande',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suivre une commande
     */
    public function track(Request $request, $orderNumber)
    {
        $order = Order::with('items.product')
            ->where('user_id', $request->user()->id)
            ->where('order_number', $orderNumber)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        $timeline = [
            [
                'status' => 'pending',
                'label' => 'Commande reçue',
                'completed' => true,
                'date' => $order->created_at
            ],
            [
                'status' => 'confirmed',
                'label' => 'Commande confirmée',
                'completed' => in_array($order->status, ['confirmed', 'processing', 'shipped', 'delivered']),
                'date' => $order->confirmed_at
            ],
            [
                'status' => 'processing',
                'label' => 'En préparation',
                'completed' => in_array($order->status, ['processing', 'shipped', 'delivered']),
                'date' => $order->processing_at
            ],
            [
                'status' => 'shipped',
                'label' => 'Expédiée',
                'completed' => in_array($order->status, ['shipped', 'delivered']),
                'date' => $order->shipped_at
            ],
            [
                'status' => 'delivered',
                'label' => 'Livrée',
                'completed' => $order->status === 'delivered',
                'date' => $order->delivered_at
            ]
        ];

        return response()->json([
            'success' => true,
            'message' => 'Suivi de commande',
            'data' => [
                'order' => $order,
                'timeline' => $timeline,
                'estimated_delivery' => $order->estimated_delivery_date
            ]
        ]);
    }

    /**
     * Confirmer la réception d'une commande
     */
    public function confirmDelivery(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée'
            ], 404);
        }

        if ($order->status !== 'shipped') {
            return response()->json([
                'success' => false,
                'message' => 'La commande n\'a pas encore été expédiée'
            ], 422);
        }

        $order->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Livraison confirmée. Merci pour votre commande !',
            'data' => $order
        ]);
    }
}