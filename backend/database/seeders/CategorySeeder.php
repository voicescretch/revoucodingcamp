<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Minuman Alkohol', 'type' => 'product'],
            ['name' => 'Minuman Non-Alkohol', 'type' => 'product'],
            ['name' => 'Makanan', 'type' => 'product'],
            ['name' => 'Bahan Baku Minuman', 'type' => 'product'],
            ['name' => 'Bahan Baku Makanan', 'type' => 'product'],
            ['name' => 'Operasional', 'type' => 'expense'],
            ['name' => 'Bahan Baku', 'type' => 'expense'],
            ['name' => 'Gaji', 'type' => 'expense'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['name' => $cat['name'], 'type' => $cat['type']]);
        }
    }
}
