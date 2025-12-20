<?php

namespace App\Http\Requests\UserManagementRequests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
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
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
            'type' => 'required|in:user,provider,store_manager,driver'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.string' => 'رقم الهاتف يجب أن يكون نصاً',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب أن تكون 8 محارف',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
            'password_confirmation.required' => 'تأكيد كلمة المرور مطلوب',
            'password_confirmation.min' => 'تأكيد كلمة المرور يجب أن تكون 8 محارف',
            'type.required' => 'نوع الحساب مطلوب',
            'type.in' => 'نوع الحساب يجب أن يكون: user, provider, store_manager,driver'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone' => 'رقم الهاتف',
            'password' => 'كلمة المرور',
            'password_confirmation' => 'تأكيد كلمة المرور',
            'type' => 'نوع الحساب'
        ];
    }
}
