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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // ✅ Relations
            $table->foreignId('category_id')
                ->constrained()
                ->cascadeOnDelete();

            // ✅ Relation vendeur
            $table->foreignId('seller_id')
                ->constrained('users') // lien vers table users
                ->cascadeOnDelete();

            // ✅ Informations principales
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');

            // ✅ Prix & stock
            $table->decimal('price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->integer('stock')->default(0);

            // ✅ Logistique
            $table->string('sku')->unique()->nullable();
            $table->decimal('weight', 8, 2)->nullable()->comment('Poids en kg');
            $table->string('dimensions')->nullable()->comment('L x l x H en cm');

            // ✅ Images Cloudinary (URLS)
            $table->text('image')->nullable()->comment('URL Cloudinary image principale');
            $table->json('images')->nullable()->comment('URLs Cloudinary images supplémentaires');

            // ✅ Mise en avant & visibilité
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);

            // ✅ Livraison
            $table->boolean('shipping_available')->default(true);
            $table->json('shipping_cities')->nullable()->comment('Villes de livraison');
            $table->decimal('shipping_cost', 10, 2)->nullable()->comment('Frais de livraison en FCFA');

            // ✅ SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();

            // ✅ Dates & suppression logique
            $table->timestamps();
            $table->softDeletes();

            // ✅ Index pour performance
            $table->index(['category_id', 'is_active']);
            $table->index('is_featured');
            $table->index('price');
            $table->index('stock');
            $table->index('seller_id'); // index sur le vendeur pour filtrer facilement
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
