<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'description',
        'category_id',
        'unit',
        'buy_price',
        'sell_price',
        'stock',
        'low_stock_threshold',
        'is_available',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'buy_price'           => 'decimal:2',
            'sell_price'          => 'decimal:2',
            'stock'               => 'decimal:4',
            'low_stock_threshold' => 'decimal:4',
            'is_available'        => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function menuRecipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'menu_product_id');
    }

    public function rawMaterialRecipes(): HasMany
    {
        return $this->hasMany(Recipe::class, 'raw_material_id');
    }
}
