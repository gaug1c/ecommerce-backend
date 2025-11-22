<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Afficher la liste des produits avec filtres et pagination
     * Adapté pour l'e-commerce au Gabon
     */
    public function index(Request $request)
    {
        $query = Product::with('category');

        // Filtre par catégorie
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Recherche par nom ou description (en français)
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filtre par plage de prix (en FCFA)
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Filtre par disponibilité
        if ($request->has('in_stock')) {
            $query->where('stock', '>', 0);
        }

        // Filtre par produits en promotion
        if ($request->has('on_sale')) {
            $query->whereNotNull('discount_price');
        }

        // Tri
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Liste des produits récupérée avec succès',
            'data' => $products,
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Afficher un produit spécifique
     */
    public function show($id)
    {
        $product = Product::with('category')->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Détails du produit',
            'data' => $product,
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Créer un nouveau produit (Admin uniquement)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'category_id' => 'required|exists:categories,id',
            'stock' => 'required|integer|min:0',
            'sku' => 'nullable|string|unique:products,sku',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'is_featured' => 'boolean',
            'shipping_available' => 'boolean',
            'shipping_cities' => 'nullable|array',
            'shipping_cost' => 'nullable|numeric|min:0'
        ], [
            'name.required' => 'Le nom du produit est obligatoire',
            'description.required' => 'La description est obligatoire',
            'price.required' => 'Le prix est obligatoire',
            'price.numeric' => 'Le prix doit être un nombre',
            'category_id.required' => 'La catégorie est obligatoire',
            'category_id.exists' => 'Cette catégorie n\'existe pas',
            'stock.required' => 'Le stock est obligatoire',
            'image.image' => 'Le fichier doit être une image',
            'image.max' => 'L\'image ne doit pas dépasser 5 Mo'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();

        // Upload image principale
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
            $data['image'] = $imagePath;
        }

        // Upload images supplémentaires
        if ($request->hasFile('images')) {
            $imagePaths = [];
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $image->store('products', 'public');
            }
            $data['images'] = json_encode($imagePaths);
        }

        // Villes de livraison pour le Gabon
        if ($request->has('shipping_cities')) {
            $data['shipping_cities'] = json_encode($request->shipping_cities);
        }

        $product = Product::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Produit créé avec succès',
            'data' => $product->load('category')
        ], 201);
    }

    /**
     * Mettre à jour un produit
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'price' => 'sometimes|required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'category_id' => 'sometimes|required|exists:categories,id',
            'stock' => 'sometimes|required|integer|min:0',
            'sku' => 'nullable|string|unique:products,sku,' . $id,
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'is_featured' => 'boolean',
            'shipping_available' => 'boolean',
            'shipping_cities' => 'nullable|array',
            'shipping_cost' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();

        // Upload nouvelle image principale
        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $imagePath = $request->file('image')->store('products', 'public');
            $data['image'] = $imagePath;
        }

        // Upload nouvelles images supplémentaires
        if ($request->hasFile('images')) {
            if ($product->images) {
                $oldImages = json_decode($product->images, true);
                foreach ($oldImages as $oldImage) {
                    Storage::disk('public')->delete($oldImage);
                }
            }
            $imagePaths = [];
            foreach ($request->file('images') as $image) {
                $imagePaths[] = $image->store('products', 'public');
            }
            $data['images'] = json_encode($imagePaths);
        }

        // Villes de livraison
        if ($request->has('shipping_cities')) {
            $data['shipping_cities'] = json_encode($request->shipping_cities);
        }

        $product->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Produit mis à jour avec succès',
            'data' => $product->load('category')
        ]);
    }

    /**
     * Supprimer un produit
     */
    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        // Supprimer les images
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        if ($product->images) {
            $images = json_decode($product->images, true);
            foreach ($images as $image) {
                Storage::disk('public')->delete($image);
            }
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produit supprimé avec succès'
        ]);
    }

    /**
     * Produits en vedette
     */
    public function featured()
    {
        $products = Product::with('category')
            ->where('is_featured', true)
            ->where('stock', '>', 0)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Produits en vedette',
            'data' => $products,
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Produits en promotion
     */
    public function onSale()
    {
        $products = Product::with('category')
            ->whereNotNull('discount_price')
            ->where('stock', '>', 0)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Produits en promotion',
            'data' => $products,
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Vérifier la disponibilité d'un produit
     */
    public function checkAvailability($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $product->id,
                'available' => $product->stock > 0,
                'stock' => $product->stock,
                'can_order' => $product->stock > 0
            ]
        ]);
    }
}