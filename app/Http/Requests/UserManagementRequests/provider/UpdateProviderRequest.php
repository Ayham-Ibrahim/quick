<?php

namespace App\Http\Requests\UserManagementRequests\provider;

use Illuminate\Support\Facades\Auth;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateProviderRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->is_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider_name' => 'nullable|string|max:255',
            'market_name'   => 'nullable|string|max:255',
            'v_location'    => 'nullable|string|max:255',
            'h_location'    => 'nullable|string|max:255',
            'phone' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('providers', 'phone')->ignore($this->provider)
            ],
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
            'provider_name' => 'اسم مزود الخدمة',
            'phone' => 'رقم الواتساب',
            'password' => 'كلمة المرور',
            'password_confirmation' => 'تأكيد كلمة المرور',
            'market_name' => 'اسم الماركت',
            'v_location' => 'الاحداثيات العمودية',
            'h_location' => 'الاحداثيات الأفقية',
            'city' => 'المدينة',
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
