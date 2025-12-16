<?php

namespace App\Http\Requests\Attribute;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeRequest extends FormRequest
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
            'name' => [
                'string',
                'max:255',
                Rule::unique('attributes')->ignore($this->attribute)
            ],
            'value' => 'sometimes|array|min:1',
            'value.*' => 'sometimes|string|max:255|distinct'
        ];
    }
}
