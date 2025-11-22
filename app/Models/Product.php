<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'discount_price',
        'category_id',
        'stock',
        'sku',
        'weight',
        'dimensions',
        'image',
        'images',
        'is_featured',
        'is_active',
        'shipping_available',
        'shipping_cities',
        'shipping_cost',
        'meta_title',
        'meta_description',
        'meta_keywords'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'weight' => 'decimal:2',
        'stock' => 'integer',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'shipping_available' => 'boolean',
        'images' => 'array',
        'shipping_cities' => 'array',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'final_price',
        'discount_percentage',
        'is_on_sale',
        'is_in_stock',
        'image_url',
        'images_urls'
    ];

    /**
     * Relation avec la catégorie
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relation avec les items du panier
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Relation avec les items de commande
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Obtenir le prix final (avec remise si applicable)
     */
    public function getFinalPriceAttribute()
    {
        return $this->discount_price ?? $this->price;
    }

    /**
     * Calculer le pourcentage de remise
     */
    public function getDiscountPercentageAttribute()
    {
        if ($this->discount_price && $this->price > 0) {
            return round((($this->price - $this->discount_price) / $this->price) * 100);
        }
        return 0;
    }

    /**
     * Vérifier si le produit est en promotion
     */
    public function getIsOnSaleAttribute()
    {
        return $this->discount_price && $this->discount_price < $this->price;
    }

    /**
     * Vérifier si le produit est en stock
     */
    public function getIsInStockAttribute()
    {
        return $this->stock > 0;
    }

    /**
     * Obtenir l'URL complète de l'image principale
     */
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return asset('images/no-image.png');
    }

    /**
     * Obtenir les URLs complètes des images supplémentaires
     */
    public function getImagesUrlsAttribute()
    {
        if ($this->images && is_array($this->images)) {
            return array_map(function($image) {
                return asset('storage/' . $image);
            }, $this->images);
        }
        return [];
    }

    /**
     * Scope pour les produits en stock
     */
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * Scope pour les produits actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour les produits en vedette
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope pour les produits en promotion
     */
    public function scopeOnSale($query)
    {
        return $query->whereNotNull('discount_price')
                     ->whereColumn('discount_price', '<', 'price');
    }

    /**
     * Scope pour rechercher des produits
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('sku', 'like', "%{$search}%");
        });
    }

    /**
     * Décrémenter le stock
     */
    public function decrementStock($quantity)
    {
        if ($this->stock >= $quantity) {
            $this->decrement('stock', $quantity);
            return true;
        }
        return false;
    }

    /**
     * Incrémenter le stock
     */
    public function incrementStock($quantity)
    {
        $this->increment('stock', $quantity);
        return true;
    }
}