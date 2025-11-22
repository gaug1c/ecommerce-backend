<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
        'status',
        'transaction_id',
        'payment_details',
        'gateway_response',
        'refunded_at',
        'refund_reason',
        'refund_amount'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'payment_details' => 'array',
        'gateway_response' => 'array',
        'refunded_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'payment_method_label',
        'status_label',
        'is_successful',
        'is_refunded'
    ];

    /**
     * Méthodes de paiement
     */
    const METHOD_CARD = 'card';
    const METHOD_MOBILE_MONEY = 'mobile_money';
    const METHOD_PAYPAL = 'paypal';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_CASH_ON_DELIVERY = 'cash_on_delivery';

    /**
     * Statuts de paiement
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Relation avec la commande
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Obtenir le libellé de la méthode de paiement
     */
    public function getPaymentMethodLabelAttribute()
    {
        $labels = [
            self::METHOD_CARD => 'Carte Bancaire',
            self::METHOD_MOBILE_MONEY => 'Mobile Money (Airtel Money / Moov Money)',
            self::METHOD_PAYPAL => 'PayPal',
            self::METHOD_BANK_TRANSFER => 'Virement Bancaire',
            self::METHOD_CASH_ON_DELIVERY => 'Paiement à la livraison',
        ];

        return $labels[$this->payment_method] ?? $this->payment_method;
    }

    /**
     * Obtenir le libellé du statut
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            self::STATUS_PENDING => 'En attente',
            self::STATUS_PROCESSING => 'En cours',
            self::STATUS_COMPLETED => 'Complété',
            self::STATUS_FAILED => 'Échoué',
            self::STATUS_REFUNDED => 'Remboursé',
            self::STATUS_CANCELLED => 'Annulé',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Vérifier si le paiement est réussi
     */
    public function getIsSuccessfulAttribute()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Vérifier si le paiement est remboursé
     */
    public function getIsRefundedAttribute()
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    /**
     * Scope pour les paiements complétés
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope pour les paiements en attente
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope pour les paiements échoués
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope pour filtrer par méthode de paiement
     */
    public function scopeByMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Marquer le paiement comme complété
     */
    public function markAsCompleted($transactionId = null, $gatewayResponse = null)
    {
        $data = [
            'status' => self::STATUS_COMPLETED,
        ];

        if ($transactionId) {
            $data['transaction_id'] = $transactionId;
        }

        if ($gatewayResponse) {
            $data['gateway_response'] = $gatewayResponse;
        }

        $this->update($data);
    }

    /**
     * Marquer le paiement comme échoué
     */
    public function markAsFailed($gatewayResponse = null)
    {
        $data = [
            'status' => self::STATUS_FAILED,
        ];

        if ($gatewayResponse) {
            $data['gateway_response'] = $gatewayResponse;
        }

        $this->update($data);
    }

    /**
     * Rembourser le paiement
     */
    public function refund($amount = null, $reason = null)
    {
        $refundAmount = $amount ?? $this->amount;

        $this->update([
            'status' => self::STATUS_REFUNDED,
            'refund_amount' => $refundAmount,
            'refund_reason' => $reason,
            'refunded_at' => now()
        ]);
    }
}