<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'current_price' => ['nullable', 'numeric', 'min:0'],
            'previous_price' => ['nullable', 'numeric', 'min:0'],
            'sub_category_id' => ['required', 'exists:sub_categories,id'],

            'images'          => 'required|array',
            'images.*.file'   => 'required|file|image|mimes:png,jpg,jpeg|max:10000|mimetypes:image/jpeg,image/png,image/jpg',

            // Product Variants (optional - for products with variations)
            'variants' => ['nullable', 'array'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],

            // Variant attributes (e.g., Color: Red, Size: XL)
            'variants.*.attributes' => ['nullable', 'array', 'min:1'],
            'variants.*.attributes.*.attribute_id' => ['required', 'exists:attributes,id'],
            'variants.*.attributes.*.attribute_value_id' => ['required', 'exists:attribute_values,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'variants.*.price.required_with' => 'سعر المتغير مطلوب',
            'variants.*.stock_quantity.required_with' => 'كمية المخزون مطلوبة',
            'variants.*.attributes.required_with' => 'يجب تحديد سمة واحدة على الأقل للمتغير',
            'variants.*.attributes.*.attribute_id.exists' => 'السمة المحددة غير موجودة',
            'variants.*.attributes.*.attribute_value_id.exists' => 'قيمة السمة غير موجودة',
        ];
    }
}
