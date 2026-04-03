<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $catMinAlk   = Category::where('name', 'Minuman Alkohol')->first();
        $catMinNon   = Category::where('name', 'Minuman Non-Alkohol')->first();
        $catMakanan  = Category::where('name', 'Makanan')->first();
        $catBahanMin = Category::where('name', 'Bahan Baku Minuman')->first();
        $catBahanMak = Category::where('name', 'Bahan Baku Makanan')->first();

        // ── Bahan Baku ────────────────────────────────────────────────────────
        $rum = Product::firstOrCreate(['sku' => 'BB-RUM-001'], [
            'name' => 'Rum', 'category_id' => $catBahanMin->id,
            'unit' => 'ml', 'buy_price' => 150, 'sell_price' => 0,
            'stock' => 2000, 'low_stock_threshold' => 300, 'is_available' => false,
        ]);

        $syrup = Product::firstOrCreate(['sku' => 'BB-SYR-001'], [
            'name' => 'Simple Syrup', 'category_id' => $catBahanMin->id,
            'unit' => 'ml', 'buy_price' => 20, 'sell_price' => 0,
            'stock' => 1500, 'low_stock_threshold' => 200, 'is_available' => false,
        ]);

        $mint = Product::firstOrCreate(['sku' => 'BB-MNT-001'], [
            'name' => 'Daun Mint', 'category_id' => $catBahanMin->id,
            'unit' => 'lembar', 'buy_price' => 500, 'sell_price' => 0,
            'stock' => 200, 'low_stock_threshold' => 30, 'is_available' => false,
        ]);

        $soda = Product::firstOrCreate(['sku' => 'BB-SOD-001'], [
            'name' => 'Soda Water', 'category_id' => $catBahanMin->id,
            'unit' => 'ml', 'buy_price' => 10, 'sell_price' => 0,
            'stock' => 5000, 'low_stock_threshold' => 500, 'is_available' => false,
        ]);

        $lime = Product::firstOrCreate(['sku' => 'BB-LIM-001'], [
            'name' => 'Perasan Jeruk Nipis', 'category_id' => $catBahanMin->id,
            'unit' => 'ml', 'buy_price' => 30, 'sell_price' => 0,
            'stock' => 800, 'low_stock_threshold' => 100, 'is_available' => false,
        ]);

        $coffee = Product::firstOrCreate(['sku' => 'BB-COF-001'], [
            'name' => 'Espresso Shot', 'category_id' => $catBahanMin->id,
            'unit' => 'shot', 'buy_price' => 3000, 'sell_price' => 0,
            'stock' => 100, 'low_stock_threshold' => 10, 'is_available' => false,
        ]);

        $milk = Product::firstOrCreate(['sku' => 'BB-MLK-001'], [
            'name' => 'Susu Segar', 'category_id' => $catBahanMin->id,
            'unit' => 'ml', 'buy_price' => 15, 'sell_price' => 0,
            'stock' => 3000, 'low_stock_threshold' => 400, 'is_available' => false,
        ]);

        $nasi = Product::firstOrCreate(['sku' => 'BB-NSI-001'], [
            'name' => 'Nasi Putih', 'category_id' => $catBahanMak->id,
            'unit' => 'porsi', 'buy_price' => 3000, 'sell_price' => 0,
            'stock' => 50, 'low_stock_threshold' => 10, 'is_available' => false,
        ]);

        $ayam = Product::firstOrCreate(['sku' => 'BB-AYM-001'], [
            'name' => 'Ayam Goreng', 'category_id' => $catBahanMak->id,
            'unit' => 'potong', 'buy_price' => 8000, 'sell_price' => 0,
            'stock' => 30, 'low_stock_threshold' => 5, 'is_available' => false,
        ]);

        // ── Menu Minuman ──────────────────────────────────────────────────────
        $mojito = Product::firstOrCreate(['sku' => 'MN-MOJ-001'], [
            'name' => 'Mojito', 'category_id' => $catMinAlk->id,
            'unit' => 'gelas', 'buy_price' => 15000, 'sell_price' => 45000,
            'stock' => 0, 'low_stock_threshold' => 0, 'is_available' => true,
        ]);

        $latte = Product::firstOrCreate(['sku' => 'MN-LAT-001'], [
            'name' => 'Cafe Latte', 'category_id' => $catMinNon->id,
            'unit' => 'gelas', 'buy_price' => 8000, 'sell_price' => 28000,
            'stock' => 0, 'low_stock_threshold' => 0, 'is_available' => true,
        ]);

        $lemonade = Product::firstOrCreate(['sku' => 'MN-LEM-001'], [
            'name' => 'Lemonade Soda', 'category_id' => $catMinNon->id,
            'unit' => 'gelas', 'buy_price' => 5000, 'sell_price' => 22000,
            'stock' => 0, 'low_stock_threshold' => 0, 'is_available' => true,
        ]);

        // ── Menu Makanan ──────────────────────────────────────────────────────
        $nasiAyam = Product::firstOrCreate(['sku' => 'MK-NSA-001'], [
            'name' => 'Nasi Ayam Goreng', 'category_id' => $catMakanan->id,
            'unit' => 'porsi', 'buy_price' => 12000, 'sell_price' => 35000,
            'stock' => 0, 'low_stock_threshold' => 0, 'is_available' => true,
        ]);

        // ── Resep (BOM) ───────────────────────────────────────────────────────
        // Mojito
        $this->recipe($mojito->id, $rum->id,   30,  'ml');
        $this->recipe($mojito->id, $syrup->id,  15,  'ml');
        $this->recipe($mojito->id, $mint->id,   6,   'lembar');
        $this->recipe($mojito->id, $soda->id,   200, 'ml');
        $this->recipe($mojito->id, $lime->id,   20,  'ml');

        // Cafe Latte
        $this->recipe($latte->id, $coffee->id, 2, 'shot');
        $this->recipe($latte->id, $milk->id,   150, 'ml');

        // Lemonade Soda
        $this->recipe($lemonade->id, $lime->id,  40,  'ml');
        $this->recipe($lemonade->id, $syrup->id, 20,  'ml');
        $this->recipe($lemonade->id, $soda->id,  180, 'ml');

        // Nasi Ayam Goreng
        $this->recipe($nasiAyam->id, $nasi->id, 1, 'porsi');
        $this->recipe($nasiAyam->id, $ayam->id, 2, 'potong');
    }

    private function recipe(int $menuId, int $rawId, float $qty, string $unit): void
    {
        Recipe::firstOrCreate(
            ['menu_product_id' => $menuId, 'raw_material_id' => $rawId],
            ['quantity_required' => $qty, 'unit' => $unit]
        );
    }
}
