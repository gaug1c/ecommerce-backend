<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Liste des produits
     */
    public function index(Request $request)
    {
        $query = Product::with('category');

        // Si l'utilisateur est un vendeur et veut voir ses produits
        if ($request->user()->role === 'seller' && $request->has('my_products')) {
            $query->where('seller_id', $request->user()->id);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->has('in_stock')) {
            $query->where('stock', '>', 0);
        }

        if ($request->has('on_sale')) {
            $query->whereNotNull('discount_price');
        }

        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products,
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Détail produit
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
            'data' => $product,
            'currency' => 'FCFA'
        ]);
    }

    /**
     * Création produit
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Vérifier que l'utilisateur est vendeur OU admin
        if (!$user->isSeller() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas la permission de créer un produit'
            ], 403);
        }

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
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'is_featured' => 'boolean',
            'shipping_available' => 'boolean',
            'shipping_cities' => 'nullable|array',
            'shipping_cost' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['image', 'images']);

        // Associer le vendeur ou admin
        if ($request->user()->isSeller() || $request->user()->isAdmin()) {
            $data['seller_id'] = $request->user()->id;
        }


        // Slug unique
        $slug = Str::slug($request->name);
        $count = Product::where('slug', 'like', "{$slug}%")->count();
        if ($count > 0) {
            $slug .= '-' . ($count + 1);
        }
        $data['slug'] = $slug;

        // Upload image principale
        if ($request->hasFile('image')) {
            $uploadedFile = Cloudinary::uploadApi()->upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'products']
            );
            $data['image'] = $uploadedFile['secure_url'];
        }

        // Upload images multiples
        if ($request->hasFile('images')) {
            $imageUrls = [];
            foreach ($request->file('images') as $image) {
                $uploaded = Cloudinary::uploadApi()->upload(
                    $image->getRealPath(),
                    ['folder' => 'products']
                );
                $imageUrls[] = $uploaded['secure_url'];
            }
            $data['images'] = json_encode($imageUrls);
        }

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
     * Mise à jour produit
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

        // Vérifier que le vendeur est le propriétaire du produit ou admin
        if ($request->user()->role === 'seller' && $product->seller_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas la permission de modifier ce produit'
            ], 403);
        }

        // Validation identique à store()
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
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
            'is_featured' => 'boolean',
            'shipping_available' => 'boolean',
            'shipping_cities' => 'nullable|array',
            'shipping_cost' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['image', 'images']);

        // Upload image principale et multiples
        if ($request->hasFile('image')) {
            if ($product->image) {
                $publicId = pathinfo(basename(parse_url($product->image, PHP_URL_PATH)), PATHINFO_FILENAME);
                Cloudinary::uploadApi()->destroy("products/$publicId");
            }
            $uploaded = Cloudinary::uploadApi()->upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'products']
            );
            $data['image'] = $uploaded['secure_url'];
        }

        if ($request->hasFile('images')) {
            if ($product->images) {
                $oldImages = json_decode($product->images, true);
                foreach ($oldImages as $oldImage) {
                    $publicId = pathinfo(basename(parse_url($oldImage, PHP_URL_PATH)), PATHINFO_FILENAME);
                    Cloudinary::uploadApi()->destroy("products/$publicId");
                }
            }
            $imageUrls = [];
            foreach ($request->file('images') as $image) {
                $uploaded = Cloudinary::uploadApi()->upload(
                    $image->getRealPath(),
                    ['folder' => 'products']
                );
                $imageUrls[] = $uploaded['secure_url'];
            }
            $data['images'] = json_encode($imageUrls);
        }

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
     * Suppression produit
     */
    public function destroy(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé'
            ], 404);
        }

        // Vérifier que le vendeur est le propriétaire ou admin
        if ($request->user()->role === 'seller' && $product->seller_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas la permission de supprimer ce produit'
            ], 403);
        }

        // Supprimer images Cloudinary
        if ($product->image) {
            $publicId = pathinfo(basename(parse_url($product->image, PHP_URL_PATH)), PATHINFO_FILENAME);
            Cloudinary::uploadApi()->destroy("products/$publicId");
        }
        if ($product->images) {
            $images = json_decode($product->images, true);
            foreach ($images as $image) {
                $publicId = pathinfo(basename(parse_url($image, PHP_URL_PATH)), PATHINFO_FILENAME);
                Cloudinary::uploadApi()->destroy("products/$publicId");
            }
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produit supprimé avec succès'
        ]);
    }
}
