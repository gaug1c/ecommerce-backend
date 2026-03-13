<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer la contrainte existante
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_method_check");

        // Recréer la contrainte avec les nouvelles valeurs
        DB::statement("
            ALTER TABLE orders
            ADD CONSTRAINT orders_payment_method_check
            CHECK (payment_method IN (
                'card',
                'mobile_money',
                'paypal',
                'bank_transfer',
                'cash_on_delivery',
                'AIRTEL',
                'MOOV'
            ))
        ");
    }

    public function down(): void
    {
        // Supprimer la nouvelle contrainte
        DB::statement("ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_method_check");

        // Restaurer l’ancienne
        DB::statement("
            ALTER TABLE orders
            ADD CONSTRAINT orders_payment_method_check
            CHECK (payment_method IN (
                'card',
                'mobile_money',
                'paypal',
                'bank_transfer',
                'cash_on_delivery'
            ))
        ");
    }
};