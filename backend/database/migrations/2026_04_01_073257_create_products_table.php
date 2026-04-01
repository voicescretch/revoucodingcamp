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
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->string('unit');
            $table->decimal('buy_price', 15, 2);
            $table->decimal('sell_price', 15, 2);
            $table->decimal('stock', 15, 2)->default(0);
            $table->decimal('low_stock_threshold', 15, 2)->default(0);
            $table->boolean('is_available')->default(true);
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->index('sku', 'idx_products_sku');
            $table->index('is_available', 'idx_products_is_available');
            $table->index('category_id', 'idx_products_category');
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
