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
        DB::statement("ALTER TYPE seller_status_type ADD VALUE IF NOT EXISTS 'rejected'");
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            //
        });
    }
};
