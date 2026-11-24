<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductStatus;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // admin check is done by ProductPolicy on the route (->can)
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'           => ['sometimes', 'string', 'max:255'],
            'sku'            => ['sometimes', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'price'          => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'status'         => ['sometimes', Rule::enum(ProductStatus::class)],
        ];
    }
}
