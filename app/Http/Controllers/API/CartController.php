<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Afficher le panier de l'utilisateur
     * Adapté pour l'e-commerce au Gabon (prix en FCFA)
     */
    public function index(Request $request)
    {
        $cart = Cart::with('items.product.category')->firstOrCreate(
            ['user_id' => $request->user()->id]
        );

        // Calculer le total et les frais de livraison
        $subtotal = 0;
        $shippingCost = 0;

        foreach ($cart->items as $item) {
            $price = $item->product->discount_price ?? $item->product->price;
            $subtotal += $item->quantity * $price;
            
            // Ajouter les frais de livraison si applicable
            if ($item->product->shipping_cost) {
                $shippingCost += $item->product->shipping_cost;
            }
        }

        $total = $subtotal + $shippingCost;

        return response()->json([
            'success' => true,
            'message' => 'Panier récupéré avec succès',
            'data' => [
                'cart' => $cart,
                'summary' => [
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'total' => $total,
                    'items_count' => $cart->items->count(),
                    'total_quantity' => $cart->items->sum('quantity')
                ]
            ],
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Ajouter un produit au panier
     */
    public function addItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ], [
            'product_id.required' => 'L\'identifiant du produit est obligatoire',
            'product_id.exists' => 'Ce produit n\'existe pas',
            'quantity.required' => 'La quantité est obligatoire',
            'quantity.min' => 'La quantité doit être au moins 1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::find($request->product_id);

        // Vérifier le stock disponible
        if ($product->stock < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Stock insuffisant. Stock disponible: ' . $product->stock
            ], 422);
        }

        $cart = Cart::firstOrCreate(
            ['user_id' => $request->user()->id]
        );

        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($cartItem) {
            // Mettre à jour la quantité
            $newQuantity = $cartItem->quantity + $request->quantity;
            
            if ($product->stock < $newQuantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock insuffisant. Stock disponible: ' . $product->stock . ', Quantité dans le panier: ' . $cartItem->quantity
                ], 422);
            }

            $cartItem->update(['quantity' => $newQuantity]);
            $message = 'Quantité mise à jour dans le panier';
        } else {
            // Créer un nouvel item
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity
            ]);
            $message = 'Produit ajouté au panier avec succès';
        }

        $cart->load('items.product.category');

        // Recalculer le total
        $subtotal = 0;
        $shippingCost = 0;
        foreach ($cart->items as $item) {
            $price = $item->product->discount_price ?? $item->product->price;
            $subtotal += $item->quantity * $price;
            if ($item->product->shipping_cost) {
                $shippingCost += $item->product->shipping_cost;
            }
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'cart' => $cart,
                'summary' => [
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'total' => $subtotal + $shippingCost,
                    'items_count' => $cart->items->count()
                ]
            ],
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Mettre à jour la quantité d'un article
     */
    public function updateItem(Request $request, $itemId)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1'
        ], [
            'quantity.required' => 'La quantité est obligatoire',
            'quantity.min' => 'La quantité doit être au moins 1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $cartItem = CartItem::whereHas('cart', function($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->find($itemId);

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Article non trouvé dans le panier'
            ], 404);
        }

        $product = $cartItem->product;

        // Vérifier le stock
        if ($product->stock < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Stock insuffisant. Stock disponible: ' . $product->stock
            ], 422);
        }

        $cartItem->update(['quantity' => $request->quantity]);

        $cart = Cart::with('items.product.category')
            ->where('user_id', $request->user()->id)
            ->first();

        // Recalculer le total
        $subtotal = 0;
        $shippingCost = 0;
        foreach ($cart->items as $item) {
            $price = $item->product->discount_price ?? $item->product->price;
            $subtotal += $item->quantity * $price;
            if ($item->product->shipping_cost) {
                $shippingCost += $item->product->shipping_cost;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Quantité mise à jour avec succès',
            'data' => [
                'cart' => $cart,
                'summary' => [
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'total' => $subtotal + $shippingCost,
                    'items_count' => $cart->items->count()
                ]
            ],
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Retirer un article du panier
     */
    public function removeItem(Request $request, $itemId)
    {
        $cartItem = CartItem::whereHas('cart', function($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->find($itemId);

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Article non trouvé dans le panier'
            ], 404);
        }

        $productName = $cartItem->product->name;
        $cartItem->delete();

        $cart = Cart::with('items.product.category')
            ->where('user_id', $request->user()->id)
            ->first();

        // Recalculer le total
        $subtotal = 0;
        $shippingCost = 0;
        if ($cart && $cart->items->count() > 0) {
            foreach ($cart->items as $item) {
                $price = $item->product->discount_price ?? $item->product->price;
                $subtotal += $item->quantity * $price;
                if ($item->product->shipping_cost) {
                    $shippingCost += $item->product->shipping_cost;
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => $productName . ' retiré du panier',
            'data' => [
                'cart' => $cart,
                'summary' => [
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'total' => $subtotal + $shippingCost,
                    'items_count' => $cart ? $cart->items->count() : 0
                ]
            ],
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Vider complètement le panier
     */
    public function clear(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();

        if ($cart) {
            $cart->items()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Panier vidé avec succès',
            'data' => [
                'cart' => $cart,
                'summary' => [
                    'subtotal' => 0,
                    'shipping_cost' => 0,
                    'total' => 0,
                    'items_count' => 0
                ]
            ],
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Vérifier la disponibilité des produits dans le panier
     */
    public function validateCart(Request $request)
    {
        $cart = Cart::with('items.product')->where('user_id', $request->user()->id)->first();

        if (!$cart || $cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Le panier est vide'
            ], 422);
        }

        $unavailableItems = [];

        foreach ($cart->items as $item) {
            if ($item->product->stock < $item->quantity) {
                $unavailableItems[] = [
                    'product' => $item->product->name,
                    'requested' => $item->quantity,
                    'available' => $item->product->stock
                ];
            }
        }

        if (!empty($unavailableItems)) {
            return response()->json([
                'success' => false,
                'message' => 'Certains produits ne sont plus disponibles en quantité suffisante',
                'unavailable_items' => $unavailableItems
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tous les produits sont disponibles',
            'data' => [
                'valid' => true
            ]
        ]);
    }
}