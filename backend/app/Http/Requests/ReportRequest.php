<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format'     => ['required', 'in:pdf,excel'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'format.required'          => 'Parameter format wajib diisi (pdf atau excel).',
            'format.in'                => 'Format harus berupa pdf atau excel.',
            'start_date.date_format'   => 'start_date harus berformat YYYY-MM-DD.',
            'end_date.date_format'     => 'end_date harus berformat YYYY-MM-DD.',
            'end_date.after_or_equal'  => 'end_date tidak boleh lebih kecil dari start_date.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 400)
        );
    }
}
