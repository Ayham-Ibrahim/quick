<?php

namespace App\Http\Requests\Complaint;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComplaintRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'min:10', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg' , 'max:10240'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'content.required' => 'محتوى الشكوى مطلوب',
            'content.min' => 'محتوى الشكوى يجب أن يكون على الأقل 10 أحرف',
            'content.max' => 'محتوى الشكوى يجب ألا يتجاوز 2000 حرف',
            'image.image' => 'الملف يجب أن يكون صورة',
            'image.mimes' => 'الصورة يجب أن تكون من نوع: jpeg, png, jpg',
            'image.max' => 'حجم الصورة يجب ألا يتجاوز 10 ميجابايت',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'content' => 'محتوى الشكوى',
            'image' => 'الصورة',
        ];
    }
}