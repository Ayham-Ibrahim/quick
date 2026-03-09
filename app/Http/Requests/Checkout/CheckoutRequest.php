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
            'is_immediate_delivery' => 'nullable|boolean',
            'requested_delivery_at' => 'nullable|date|after_or_equal:now|exclude_if:is_immediate_delivery,true|required_if:is_immediate_delivery,false',
            'distance_km' => 'required|numeric|max:100', // المسافة لأبعد متجر
            'notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    protected function prepareForValidation()
    {
        // convert empty delivery time to null so "after" rule doesn't trigger
        if ($this->has('requested_delivery_at') && $this->requested_delivery_at === '') {
            $this->merge(['requested_delivery_at' => null]);
        }
    }

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
            'is_immediate_delivery.boolean' => 'حقل التوصيل الفوري يجب أن يكون صحيح أو خطأ',
            'requested_delivery_at.after_or_equal' => 'موعد التوصيل يجب أن يكون الآن أو في المستقبل',
            'requested_delivery_at.required_if' => 'يجب إدخال موعد التوصيل عند اختيار طلب مجدول',
            'distance_km.required' => 'يجب إدخال المسافة لحساب رسوم التوصيل',
            'distance_km.numeric' => 'المسافة يجب أن تكون رقماً',
            'distance_km.max' => 'المسافة لا يمكن أن تتجاوز 100 كم',
            'coupon_code.max' => 'كود الكوبون طويل جداً',
            'notes.max' => 'الملاحظات طويلة جداً',
        ];
    }
}
