<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Attributs assignables en masse
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
     * Casts automatiques
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
        'images' => 'array',           // ✅ JSON → array
        'shipping_cities' => 'array',  // ✅ JSON → array
    ];

    /**
     * Attributs calculés retournés dans l'API
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
     * =========================
     * ✅ RELATIONS
     * =========================
     */

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * =========================
     * ✅ ACCESSORS
     * =========================
     */

    // ✅ Prix final
    public function getFinalPriceAttribute()
    {
        return $this->discount_price ?? $this->price;
    }

    // ✅ Pourcentage de remise
    public function getDiscountPercentageAttribute()
    {
        if ($this->discount_price && $this->price > 0) {
            return round((($this->price - $this->discount_price) / $this->price) * 100);
        }
        return 0;
    }

    // ✅ Produit en promotion ?
    public function getIsOnSaleAttribute()
    {
        return $this->discount_price && $this->discount_price < $this->price;
    }

    // ✅ Produit en stock ?
    public function getIsInStockAttribute()
    {
        return $this->stock > 0;
    }

    // ✅ URL Cloudinary de l’image principale
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return $this->image; // ✅ Déjà une URL Cloudinary
        }
        return 'https://res.cloudinary.com/demo/image/upload/v1699999999/no-image.png';
    }

    // ✅ URLs Cloudinary des images supplémentaires
    public function getImagesUrlsAttribute()
    {
        if ($this->images && is_array($this->images)) {
            return $this->images; // ✅ Déjà un tableau d’URLs
        }
        return [];
    }

    /**
     * =========================
     * ✅ SCOPES
     * =========================
     */

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOnSale($query)
    {
        return $query->whereNotNull('discount_price')
                     ->whereColumn('discount_price', '<', 'price');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('sku', 'like', "%{$search}%");
        });
    }

    /**
     * =========================
     * ✅ GESTION DU STOCK
     * =========================
     */

    public function decrementStock($quantity)
    {
        if ($this->stock >= $quantity) {
            $this->decrement('stock', $quantity);
            return true;
        }
        return false;
    }

    public function incrementStock($quantity)
    {
        $this->increment('stock', $quantity);
        return true;
    }
}
