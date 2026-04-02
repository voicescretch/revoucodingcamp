<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_code'     => ['required', 'string'],
            'payment_method' => ['required', 'in:cash,card,qris'],
            'paid_amount'    => ['required', 'numeric', 'min:0'],
        ];
    }
}
