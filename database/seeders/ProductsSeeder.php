<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Str;

class ProductsSeeder extends Seeder
{
    public function run(): void
    {
        // Récupérer un utilisateur qui a le rôle seller ou admin via la table user_roles
        $seller = User::whereHas('roles', function($query) {
            $query->whereIn('name', ['seller', 'admin']);
        })->first();
        
        // Si aucun vendeur trouvé, prendre le premier utilisateur
        if (!$seller) {
            $seller = User::first();
            
            if (!$seller) {
                $this->command->error('❌ Aucun utilisateur trouvé. Créez d\'abord un utilisateur.');
                return;
            }
            
            $this->command->warn('⚠️  Aucun vendeur trouvé. Utilisation de: ' . $seller->name);
        } else {
            $this->command->info("👤 Vendeur: {$seller->name} (ID: {$seller->id})");
        }

        // Créer catégories
        $this->command->info("\n📁 Création des catégories...");
        
        $categoryData = [
            ['name' => 'Vêtements', 'slug' => 'vetements'],
            ['name' => 'Accessoires', 'slug' => 'accessoires'],
            ['name' => 'Montres', 'slug' => 'montres'],
            ['name' => 'Audio', 'slug' => 'audio'],
            ['name' => 'Cuisine', 'slug' => 'cuisine'],
            ['name' => 'Beauté', 'slug' => 'beaute'],
            ['name' => 'Cadeaux', 'slug' => 'cadeaux'],
        ];

        $categories = [];
        foreach ($categoryData as $catData) {
            $category = Category::firstOrCreate(
                ['slug' => $catData['slug']],
                ['name' => $catData['name'], 'is_active' => true]
            );
            $categories[$catData['name']] = $category;
        }

        $this->command->info("✓ " . count($categories) . " catégories OK\n");

        // Produits
        $this->command->info("📦 Importation des produits...\n");
        
        $products = [
            ['name' => 'Ensemble Nike Tech Fleece', 'description' => 'Ensemble complet Nike Tech Fleece avec veste à capuche zippée et pantalon de jogging. Tissu technique respirant et confortable.', 'price' => 120, 'category' => 'Vêtements', 'stock' => 50, 'sku' => 'NK-TF-001', 'weight' => 0.8, 'dimensions' => 'S, M, L, XL, XXL', 'is_featured' => true],
            ['name' => 'Ensemble Nike Half-Zip', 'description' => 'Ensemble Nike avec sweatshirt demi-zip et pantalon de jogging. Design minimaliste avec logo Nike brodé.', 'price' => 100, 'category' => 'Vêtements', 'stock' => 45, 'sku' => 'NK-HZ-001', 'weight' => 0.7, 'dimensions' => 'S, M, L, XL, XXL', 'is_featured' => true],
            ['name' => 'Ensemble Nike Colorblock', 'description' => 'Ensemble Nike à capuche avec design colorblock distinctif. Veste zippée et pantalon assorti.', 'price' => 110, 'category' => 'Vêtements', 'stock' => 40, 'sku' => 'NK-CB-001', 'weight' => 0.75, 'dimensions' => 'S, M, L, XL, XXL', 'is_featured' => true],
            ['name' => 'Ensemble Polo U.S.PA Rugby', 'description' => 'Ensemble polo style rugby avec rayures horizontales et col zippé. Logo U.S. POLO ASSN. brodé.', 'price' => 100, 'category' => 'Vêtements', 'stock' => 35, 'sku' => 'USPA-RG-001', 'weight' => 0.7, 'dimensions' => 'S, M, L, XL, XXL'],
            ['name' => 'Support Téléphone Magnétique', 'description' => 'Support téléphone magnétique rotatif 360° pour voiture. Compatible tous smartphones.', 'price' => 25, 'category' => 'Accessoires', 'stock' => 100, 'sku' => 'ACC-SP-001', 'weight' => 0.15, 'shipping_cost' => 5],
            ['name' => 'Abaya Moderne avec Poches', 'description' => 'Abaya élégante avec poches latérales et manches kimono. Tissu fluide et confortable.', 'price' => 80, 'category' => 'Vêtements', 'stock' => 30, 'sku' => 'ABY-001', 'weight' => 0.5, 'dimensions' => 'S, M, L, XL'],
            ['name' => 'Montre Datejust Or/Acier', 'description' => 'Montre style Datejust avec lunette cannelée, bracelet jubilé bicolore. Cadran champagne avec index diamants.', 'price' => 150, 'category' => 'Montres', 'stock' => 20, 'sku' => 'MON-DJ-001', 'weight' => 0.2, 'is_featured' => true, 'shipping_cost' => 10],
            ['name' => 'Montre Datejust Cadran Bleu', 'description' => 'Montre style Datejust avec cadran bleu foncé et index diamants. Bracelet jubilé bicolore or/acier.', 'price' => 150, 'category' => 'Montres', 'stock' => 15, 'sku' => 'MON-DJ-002', 'weight' => 0.2, 'is_featured' => true, 'shipping_cost' => 10],
            ['name' => 'Montre Datejust Cadran Vert', 'description' => 'Montre style Datejust avec cadran vert olive et index diamants. Design élégant et intemporel.', 'price' => 150, 'category' => 'Montres', 'stock' => 15, 'sku' => 'MON-DJ-003', 'weight' => 0.2, 'shipping_cost' => 10],
            ['name' => 'Ensemble ZARA Colorblock', 'description' => 'Ensemble ZARA avec veste zippée colorblock et pantalon assorti. Design moderne.', 'price' => 90, 'category' => 'Vêtements', 'stock' => 40, 'sku' => 'ZR-CB-001', 'weight' => 0.7, 'dimensions' => 'S, M, L, XL, XXL'],
            ['name' => 'Ensemble Polo Ralph Lauren', 'description' => 'Ensemble Polo Ralph Lauren blanc avec sweatshirt demi-zip. Logo emblématique brodé.', 'price' => 130, 'category' => 'Vêtements', 'stock' => 25, 'sku' => 'RL-PL-001', 'weight' => 0.8, 'dimensions' => 'S, M, L, XL, XXL', 'is_featured' => true],
            ['name' => 'Enceinte Bluetooth Oraimo', 'description' => 'Enceinte Bluetooth portable avec son stéréo puissant et éclairage RGB. Autonomie 365 jours.', 'price' => 60, 'category' => 'Audio', 'stock' => 50, 'sku' => 'AUD-OR-001', 'weight' => 0.6, 'is_featured' => true, 'shipping_cost' => 5],
            ['name' => 'Casque BL30 ANC', 'description' => 'Casque Bluetooth sans fil avec réduction de bruit active ANC 3ème génération.', 'price' => 45, 'category' => 'Audio', 'stock' => 60, 'sku' => 'AUD-BL30-001', 'weight' => 0.3, 'shipping_cost' => 5],
            ['name' => 'Batteur Électrique', 'description' => 'Batteur électrique 7 vitesses. Fouets acier inoxydable inclus. Parfait pâtisserie.', 'price' => 35, 'category' => 'Cuisine', 'stock' => 40, 'sku' => 'CUI-BAT-001', 'weight' => 0.8, 'shipping_cost' => 5],
            ['name' => 'Râpe Rotative 3 Tambours', 'description' => 'Râpe rotative manuelle avec 3 tambours inox. Base ventouse stable.', 'price' => 28, 'category' => 'Cuisine', 'stock' => 35, 'sku' => 'CUI-RAP-001', 'weight' => 0.6, 'shipping_cost' => 5],
            ['name' => 'Panini Maker 2000W', 'description' => 'Grill panini électrique 2000W plaques antiadhésives. Garantie 12 mois.', 'price' => 55, 'category' => 'Cuisine', 'stock' => 25, 'sku' => 'CUI-PAN-001', 'weight' => 2.5, 'shipping_cost' => 10],
            ['name' => 'Coupe-Légumes 8 en 1', 'description' => 'Coupe-légumes avec 8 lames interchangeables. Conteneur intégré.', 'price' => 32, 'category' => 'Cuisine', 'stock' => 45, 'sku' => 'CUI-CLG-001', 'weight' => 1.2, 'shipping_cost' => 5],
            ['name' => 'Lime Électrique Pieds', 'description' => 'Lime électrique pédicure rechargeable 2 têtes. Double vitesse.', 'price' => 38, 'category' => 'Beauté', 'stock' => 30, 'sku' => 'BEA-LIM-001', 'weight' => 0.3, 'shipping_cost' => 5],
            ['name' => 'Épilateur Capsule Blawless', 'description' => 'Épilateur capsule USB. Format rouge à lèvres. Tête 18K dorée.', 'price' => 42, 'category' => 'Beauté', 'stock' => 50, 'sku' => 'BEA-EPI-001', 'weight' => 0.15, 'is_featured' => true, 'shipping_cost' => 5],
            ['name' => 'Coffret Coran Rose', 'description' => 'Coffret: Coran couverture dorée, tapis velours rose, chapelet.', 'price' => 65, 'category' => 'Cadeaux', 'stock' => 20, 'sku' => 'CAD-COR-001', 'weight' => 1.5, 'shipping_cost' => 10],
            ['name' => 'Coffret Coran Bleu', 'description' => 'Coffret: Coran bleu royal, tapis prière franges turquoise, chapelet.', 'price' => 65, 'category' => 'Cadeaux', 'stock' => 20, 'sku' => 'CAD-COR-003', 'weight' => 1.5, 'shipping_cost' => 10],
        ];

        $imported = 0;
        foreach ($products as $p) {
            try {
                $slug = Str::slug($p['name']);
                $count = Product::where('slug', 'like', "{$slug}%")->count();
                if ($count > 0) $slug .= '-' . ($count + 1);

                Product::create([
                    'name' => $p['name'],
                    'slug' => $slug,
                    'description' => $p['description'],
                    'price' => $p['price'],
                    'category_id' => $categories[$p['category']]->id,
                    'stock' => $p['stock'],
                    'sku' => $p['sku'],
                    'weight' => $p['weight'] ?? null,
                    'dimensions' => $p['dimensions'] ?? null,
                    'is_featured' => $p['is_featured'] ?? false,
                    'shipping_available' => true,
                    'shipping_cost' => $p['shipping_cost'] ?? 0,
                    'seller_id' => $seller->id,
                    'is_active' => true,
                ]);

                $imported++;
                $this->command->info("  ✓ {$p['name']}");
            } catch (\Exception $e) {
                $this->command->error("  ✗ {$p['name']}: " . $e->getMessage());
            }
        }

        $this->command->info("\n" . str_repeat('=', 60));
        $this->command->info("🎉 {$imported} produits importés!");
        $this->command->info(str_repeat('=', 60) . "\n");
    }
}