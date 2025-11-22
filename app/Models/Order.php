<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'order_number',
        'subtotal',
        'shipping_cost',
        'tax',
        'discount',
        'total_amount',
        'status',
        'payment_status',
        'payment_method',
        'shipping_address',
        'shipping_city',
        'shipping_postal_code',
        'shipping_country',
        'phone',
        'delivery_instructions',
        'tracking_number',
        'estimated_delivery_date',
        'confirmed_at',
        'processing_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
        'notes'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'estimated_delivery_date' => 'date',
        'confirmed_at' => 'datetime',
        'processing_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'status_label',
        'payment_status_label',
        'can_be_cancelled',
        'full_shipping_address'
    ];

    /**
     * Statuts possibles de commande
     */
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_FAILED = 'failed';

    /**
     * Statuts de paiement
     */
    const PAYMENT_PENDING = 'pending';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_FAILED = 'failed';
    const PAYMENT_REFUNDED = 'refunded';

    /**
     * Relation avec l'utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relation avec les items de commande
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Relation avec le paiement
     */
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Obtenir le libellé du statut
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            self::STATUS_PENDING => 'En attente',
            self::STATUS_CONFIRMED => 'Confirmée',
            self::STATUS_PROCESSING => 'En préparation',
            self::STATUS_SHIPPED => 'Expédiée',
            self::STATUS_DELIVERED => 'Livrée',
            self::STATUS_CANCELLED => 'Annulée',
            self::STATUS_REFUNDED => 'Remboursée',
            self::STATUS_FAILED => 'Échouée',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Obtenir le libellé du statut de paiement
     */
    public function getPaymentStatusLabelAttribute()
    {
        $labels = [
            self::PAYMENT_PENDING => 'En attente',
            self::PAYMENT_PAID => 'Payée',
            self::PAYMENT_FAILED => 'Échouée',
            self::PAYMENT_REFUNDED => 'Remboursée',
        ];

        return $labels[$this->payment_status] ?? $this->payment_status;
    }

    /**
     * Vérifier si la commande peut être annulée
     */
    public function getCanBeCancelledAttribute()
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_PROCESSING
        ]);
    }

    /**
     * Obtenir l'adresse complète de livraison
     */
    public function getFullShippingAddressAttribute()
    {
        $parts = array_filter([
            $this->shipping_address,
            $this->shipping_city,
            $this->shipping_postal_code,
            $this->shipping_country
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Scope pour les commandes d'un utilisateur
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope pour filtrer par statut
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope pour les commandes en attente
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope pour les commandes confirmées
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Scope pour les commandes en cours
     */
    public function scopeInProgress($query)
    {
        return $query->whereIn('status', [
            self::STATUS_CONFIRMED,
            self::STATUS_PROCESSING,
            self::STATUS_SHIPPED
        ]);
    }

    /**
     * Scope pour les commandes livrées
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    /**
     * Scope pour les commandes annulées
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Confirmer la commande
     */
    public function confirm()
    {
        $this->update([
            'status' => self::STATUS_CONFIRMED,
            'confirmed_at' => now()
        ]);
    }

    /**
     * Mettre en préparation
     */
    public function process()
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'processing_at' => now()
        ]);
    }

    /**
     * Expédier la commande
     */
    public function ship($trackingNumber = null)
    {
        $data = [
            'status' => self::STATUS_SHIPPED,
            'shipped_at' => now()
        ];

        if ($trackingNumber) {
            $data['tracking_number'] = $trackingNumber;
        }

        $this->update($data);
    }

    /**
     * Marquer comme livrée
     */
    public function deliver()
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
            'payment_status' => self::PAYMENT_PAID
        ]);
    }

    /**
     * Annuler la commande
     */
    public function cancel($reason = null)
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason
        ]);
    }

    /**
     * Calculer le nombre total d'articles
     */
    public function getTotalItemsAttribute()
    {
        return $this->items->sum('quantity');
    }
}