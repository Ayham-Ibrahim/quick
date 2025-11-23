<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'current_price' => ['nullable', 'numeric', 'min:0'],
            'previous_price' => ['nullable', 'numeric', 'min:0'],
            'sub_category_id' => ['nullable', 'exists:sub_categories,id'],

            'images'          => 'nullable|array',
            'images.*.file'   => 'nullable|file|image|mimes:png,jpg,jpeg|max:10000|mimetypes:image/jpeg,image/png,image/jpg',
        ];
    }
}
