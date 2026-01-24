<?php

namespace App\Http\Requests\Checkout;

use App\Http\Requests\BaseFormRequest;

class CheckoutRequest extends BaseFormRequest
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
            'coupon_code' => 'nullable|string|max:50',
            'delivery_address' => 'required|string|max:500',
            'delivery_lat' => 'required|numeric|between:-90,90',
            'delivery_lng' => 'required|numeric|between:-180,180',
            'requested_delivery_at' => 'nullable|date|after:now',
            'delivery_fee' => 'nullable|numeric|min:0', // جاهز للاستخدام المستقبلي
            'notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'delivery_address.required' => 'يجب إدخال عنوان التوصيل',
            'delivery_address.max' => 'عنوان التوصيل طويل جداً',
            'delivery_lat.required' => 'يجب إدخال احداثيات الموقع (خط العرض)',
            'delivery_lat.numeric' => 'خط العرض يجب أن يكون رقماً',
            'delivery_lat.between' => 'خط العرض يجب أن يكون بين -90 و 90',
            'delivery_lng.required' => 'يجب إدخال احداثيات الموقع (خط الطول)',
            'delivery_lng.numeric' => 'خط الطول يجب أن يكون رقماً',
            'delivery_lng.between' => 'خط الطول يجب أن يكون بين -180 و 180',
            'requested_delivery_at.after' => 'موعد التوصيل يجب أن يكون في المستقبل',
            'delivery_fee.numeric' => 'رسوم التوصيل يجب أن تكون رقماً',
            'delivery_fee.min' => 'رسوم التوصيل لا يمكن أن تكون سالبة',
            'coupon_code.max' => 'كود الكوبون طويل جداً',
            'notes.max' => 'الملاحظات طويلة جداً',
        ];
    }
}
