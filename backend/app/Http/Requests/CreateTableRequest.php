<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_number' => ['required', 'string', 'unique:tables,table_number'],
            'name'         => ['required', 'string'],
            'capacity'     => ['nullable', 'integer', 'min:1'],
            'status'       => ['nullable', 'in:available,occupied,reserved'],
        ];
    }
}
