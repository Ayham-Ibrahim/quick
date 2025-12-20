<?php

namespace App\Http\Requests\UserManagementRequests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmForgotPasswordRequest extends FormRequest
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
            'otp_code' => 'required|string|size:4',
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
            'otp_code.required' => 'رمز التحقق مطلوب',
            'otp_code.string' => 'رمز التحقق يجب أن يكون نصاً',
            'otp_code.size' => 'رمز التحقق يجب أن يكون مكوناً من 4 أرقام',
            'type.required' => 'نوع الحساب مطلوب',
            'type.in' => 'نوع الحساب يجب أن يكون: user, provider, store_manager,driver'
        ];      
    }

    public function attributes(): array
    {
        return [
            'phone' => 'رقم الهاتف',
            'otp_code' => 'رمز التحقق',
            'type' => 'نوع الحساب'
        ];
    }
}