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
        ];
    }
}
