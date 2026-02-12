<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE seller_profiles
            DROP CONSTRAINT IF EXISTS seller_profiles_seller_status_check
        ");

        DB::statement("
            ALTER TABLE seller_profiles
            ADD CONSTRAINT seller_profiles_seller_status_check
            CHECK (seller_status IN ('pending','active','blocked','rejected'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE seller_profiles
            DROP CONSTRAINT IF EXISTS seller_profiles_seller_status_check
        ");

        DB::statement("
            ALTER TABLE seller_profiles
            ADD CONSTRAINT seller_profiles_seller_status_check
            CHECK (seller_status IN ('pending','active','blocked'))
        ");
    }
};
