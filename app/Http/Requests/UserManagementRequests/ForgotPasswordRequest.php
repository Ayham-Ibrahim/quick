<?php

namespace App\Http\Requests\UserManagementRequests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
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
            'phone' => 'required|string',
            'type' => 'required|in:user,provider,store_manager'
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.string' => 'رقم الهاتف يجب أن يكون نصاً',
            'type.required' => 'نوع الحساب مطلوب',
            'type.in' => 'نوع الحساب يجب أن يكون: user, provider, store'
        ];
    }

    public function attributes(): array
    {
        return [
            'phone' => 'رقم الهاتف',
            'type' => 'نوع الحساب'
        ];
    }
}
