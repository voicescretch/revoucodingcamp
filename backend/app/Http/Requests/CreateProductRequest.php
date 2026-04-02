<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku'                 => ['required', 'string', 'unique:products,sku'],
            'name'                => ['required', 'string'],
            'category_id'         => ['nullable', 'exists:categories,id'],
            'unit'                => ['required', 'string'],
            'buy_price'           => ['required', 'numeric', 'min:0'],
            'sell_price'          => ['required', 'numeric', 'min:0'],
            'stock'               => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0'],
            'is_available'        => ['nullable', 'boolean'],
            'description'         => ['nullable', 'string'],
            'image_path'          => ['nullable', 'string'],
        ];
    }
}
