<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->enum('payment_method', [
                'card',
                'mobile_money',
                'paypal',
                'bank_transfer',
                'cash_on_delivery'
            ]);
            $table->decimal('amount', 10, 2);
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'refunded',
                'cancelled'
            ])->default('pending');
            $table->string('transaction_id')->unique()->nullable();
            $table->json('payment_details')->nullable()->comment('Détails spécifiques au moyen de paiement');
            $table->json('gateway_response')->nullable()->comment('Réponse de la passerelle de paiement');
            $table->timestamp('refunded_at')->nullable();
            $table->text('refund_reason')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->timestamps();

            $table->index('transaction_id');
            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};