<?php

namespace App\Http\Requests\Checkout;

use App\Http\Requests\BaseFormRequest;

class ValidateCouponRequest extends BaseFormRequest
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
            'coupon_code' => 'required|string|max:50',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'coupon_code.required' => 'يجب إدخال كود الكوبون',
            'coupon_code.max' => 'كود الكوبون طويل جداً',
        ];
    }
}
