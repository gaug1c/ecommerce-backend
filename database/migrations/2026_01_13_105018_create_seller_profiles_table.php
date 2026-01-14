<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->string('shop_name')->unique();
                $table->string('shop_address')->nullable();
                $table->string('shop_city')->nullable();
                $table->string('shop_country')->default('Gabon');
                $table->string('shop_postal_code')->nullable();

                $table->string('id_card_path')->nullable();
                $table->enum('id_card_status', ['pending', 'verified', 'rejected'])
                    ->default('pending');

                $table->enum('seller_status', ['pending', 'active', 'blocked'])
                    ->default('pending');

                $table->timestamps();
            });
        }

        public function down(): void
        {
            Schema::dropIfExists('seller_profiles');
        }
    };