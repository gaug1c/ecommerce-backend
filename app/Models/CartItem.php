<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'subtotal',
        'unit_price',
        'is_available'
    ];

    /**
     * Relation avec le panier
     */
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Relation avec le produit
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Obtenir le prix unitaire (avec remise si applicable)
     */
    public function getUnitPriceAttribute()
    {
        if ($this->product) {
            return $this->product->discount_price ?? $this->product->price;
        }
        return 0;
    }

    /**
     * Calculer le sous-total de l'item
     */
    public function getSubtotalAttribute()
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * Vérifier si le produit est disponible en quantité suffisante
     */
    public function getIsAvailableAttribute()
    {
        if ($this->product) {
            return $this->product->stock >= $this->quantity;
        }
        return false;
    }

    /**
     * Augmenter la quantité
     */
    public function increaseQuantity($amount = 1)
    {
        $newQuantity = $this->quantity + $amount;
        
        if ($this->product && $this->product->stock >= $newQuantity) {
            $this->update(['quantity' => $newQuantity]);
            return true;
        }
        
        return false;
    }

    /**
     * Diminuer la quantité
     */
    public function decreaseQuantity($amount = 1)
    {
        $newQuantity = $this->quantity - $amount;
        
        if ($newQuantity <= 0) {
            $this->delete();
            return true;
        }
        
        $this->update(['quantity' => $newQuantity]);
        return true;
    }

    /**
     * Mettre à jour la quantité
     */
    public function updateQuantity($quantity)
    {
        if ($quantity <= 0) {
            $this->delete();
            return true;
        }
        
        if ($this->product && $this->product->stock >= $quantity) {
            $this->update(['quantity' => $quantity]);
            return true;
        }
        
        return false;
    }
}