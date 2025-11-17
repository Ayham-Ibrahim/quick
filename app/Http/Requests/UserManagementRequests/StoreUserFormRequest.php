<?php

namespace App\Http\Requests\UserManagementRequests;

use App\Http\Requests\BaseFormRequest;

class StoreUserFormRequest extends BaseFormRequest
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
            'name'     => 'required|string|max:255',
            'phone'    => 'required|string|max:255|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
            'avatar'   => 'nullable|image
                                    |mimes:png,jpg,jpeg
                                    |mimetypes:image/jpeg,image/png,image/jpg
                                    |max:10000',
            'gender' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'v_location' => 'required|string|max:255',
            'h_location' => 'required|string|max:255',
            'is_admin' => 'nullable|boolean|in:0,1',
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
            'name' => 'الاسم',
            'phone' => 'رقم الوااتساب',
            'password' => 'كلمة المرور',
            'password_confirmation' => 'تأكيد كلمة المرور',
            'avatar' => 'الصورة',
            'v_location' => 'الاحداثيات العمودية',
            'h_location' => 'الاحداثيات الأفقية',
            'is_admin' => 'الدور'
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
            'unique' => ':attribute مسجل مسبقاً.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min حرف/أحرف.',

            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',

            'avatar.image' => 'حقل :attribute يجب أن يكون صورة.',
            'avatar.mimes' => 'الصورة يجب أن تكون من نوع: :values.',
            'avatar.max' => 'حجم :attribute يجب ألا يتجاوز :max كيلوبايت (ما يعادل 10 ميجابايت).',
            'avatar.mimetypes' => 'نوع ملف الصورة غير مسموح به. الأنواع المسموحة: :values.',
        ];
    }
}
