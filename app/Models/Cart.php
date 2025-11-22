<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'session_id'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'subtotal',
        'total_shipping',
        'total',
        'items_count',
        'total_quantity'
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec les items du panier
     */
    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Calculer le sous-total du panier
     */
    public function getSubtotalAttribute()
    {
        return $this->items->sum(function($item) {
            $price = $item->product->discount_price ?? $item->product->price;
            return $item->quantity * $price;
        });
    }

    /**
     * Calculer le total des frais de livraison
     */
    public function getTotalShippingAttribute()
    {
        return $this->items->sum(function($item) {
            return $item->product->shipping_cost ?? 0;
        });
    }

    /**
     * Calculer le total du panier
     */
    public function getTotalAttribute()
    {
        return $this->subtotal + $this->total_shipping;
    }

    /**
     * Obtenir le nombre d'items
     */
    public function getItemsCountAttribute()
    {
        return $this->items->count();
    }

    /**
     * Obtenir la quantité totale d'articles
     */
    public function getTotalQuantityAttribute()
    {
        return $this->items->sum('quantity');
    }

    /**
     * Vider le panier
     */
    public function clear()
    {
        $this->items()->delete();
    }

    /**
     * Vérifier si le panier est vide
     */
    public function isEmpty()
    {
        return $this->items->isEmpty();
    }

    /**
     * Vérifier si tous les produits sont disponibles
     */
    public function allItemsAvailable()
    {
        foreach ($this->items as $item) {
            if ($item->product->stock < $item->quantity) {
                return false;
            }
        }
        return true;
    }

    /**
     * Obtenir les items non disponibles
     */
    public function getUnavailableItems()
    {
        return $this->items->filter(function($item) {
            return $item->product->stock < $item->quantity;
        });
    }
}