<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cafe_id' => ['required', 'exists:m_cafes,unique_id'],
            'table_id' => ['nullable', 'exists:m_cafe_tables,id'],
            'cust_name' => ['required', 'string'],
            'payment_type' => ['required', 'in:manual,qris'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.menu_id' => ['required', 'exists:m_menus,id'],
            'details.*.amount' => ['required', 'integer', 'min:1'],
            'details.*.description' => ['nullable', 'string'],
        ];
    }
}
