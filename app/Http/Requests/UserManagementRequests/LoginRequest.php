<?php

namespace App\Http\Requests\UserManagementRequests;

use App\Http\Requests\BaseFormRequest;

class LoginRequest extends BaseFormRequest
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
            'phone'    => 'required|string|max:255',
            'password' => 'required|string|min:6|max:255',
            'type' => 'required|in:user,provider,store_manager,driver'
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
            'phone' => 'رقم الواتساب',
            'password' => 'كلمة المرور',
            'type' => 'نوع المستخدم'
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
            'required' => 'حقل :attribute مطلوب.',
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
            'max' => 'حقل :attribute يجب ألا يتجاوز :max حرف/أحرف.',
            'exists' => ':attribute غير مسجل.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min حرف/أحرف.',
          ];
    }
}
