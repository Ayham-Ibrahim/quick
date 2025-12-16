<?php

namespace App\Http\Requests\UserManagementRequests;

use Illuminate\Support\Facades\Auth;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateUserProfileRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'avatar'   => 'nullable|image
                                    |mimes:png,jpg,jpeg
                                    |mimetypes:image/jpeg,image/png,image/jpg
                                    |max:5000',
            'v_location'    => 'nullable|string|max:255',
            'h_location'    => 'nullable|string|max:255',
            'phone' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'phone')->ignore(Auth::id())
            ],
            'gender' => 'nullable|string|max:255|in:male,female',
            'city'          => 'nullable|string|max:255',
            'password'      => 'nullable|string|min:6|max:255|confirmed',
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
            'name' => 'اسم المستخدم',
            'phone' => 'رقم الواتساب',
            'password' => 'كلمة المرور',
            'password_confirmation' => 'تأكيد كلمة المرور',
            'v_location' => 'الاحداثيات العمودية',
            'h_location' => 'الاحداثيات الأفقية',
            'city' => 'المدينة',
            'avatar' => 'الصورة الشخصية',
            'gender' => 'الجنس',
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
            'string' => 'حقل :attribute يجب أن يكون نصاً.',
            'max' => 'حقل :attribute يجب ألا يتجاوز :max حرف/أحرف.',
            'unique' => ':attribute مسجل مسبقاً.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min حرف/أحرف.',

            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
            'in' => 'حقل :attribute يجب أن يكون أحد قيمتين ذكر أو أنثى',

            'avatar.image' => 'حقل :attribute يجب أن يكون صورة.',
            'avatar.mimes' => 'الصورة يجب أن تكون من نوع: :values.',
            'avatar.max' => 'حجم :attribute يجب ألا يتجاوز :max كيلوبايت (ما يعادل 5 ميجابايت).',
            'avatar.mimetypes' => 'نوع ملف الصورة غير مسموح به. الأنواع المسموحة: :values.',
        ];
    }
    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'غير مصرح لك بالقيام بهذا الإجراء.'
        ], 403));
    }
}
