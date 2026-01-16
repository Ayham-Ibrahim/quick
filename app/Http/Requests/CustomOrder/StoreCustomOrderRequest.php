<?php

namespace App\Http\Requests\CustomOrder;

use App\Http\Requests\BaseFormRequest;

class StoreCustomOrderRequest extends BaseFormRequest
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
            // معلومات التوصيل
            'delivery_address' => 'required|string|max:500',
            'delivery_lat' => 'nullable|numeric|between:-90,90',
            'delivery_lng' => 'nullable|numeric|between:-180,180',
            'distance_km' => 'required|numeric|min:0.1|max:100',

            // موعد التوصيل
            'is_immediate' => 'boolean',
            'scheduled_at' => 'nullable|required_if:is_immediate,false|date|after:now',

            // العناصر
            'items' => 'required|array|min:1|max:10',
            'items.*.description' => 'required|string|max:1000',
            'items.*.pickup_address' => 'required|string|max:500',
            'items.*.pickup_lat' => 'nullable|numeric|between:-90,90',
            'items.*.pickup_lng' => 'nullable|numeric|between:-180,180',

            // ملاحظات
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'delivery_address.required' => 'عنوان التسليم مطلوب',
            'distance_km.required' => 'المسافة مطلوبة لحساب سعر التوصيل',
            'distance_km.min' => 'المسافة يجب أن تكون أكبر من 0.1 كم',
            'items.required' => 'يجب إضافة طلب واحد على الأقل',
            'items.min' => 'يجب إضافة طلب واحد على الأقل',
            'items.max' => 'الحد الأقصى 10 طلبات',
            'items.*.description.required' => 'وصف الطلب مطلوب',
            'items.*.pickup_address.required' => 'موقع الاستلام مطلوب',
            'scheduled_at.required_if' => 'يجب تحديد موعد التوصيل',
            'scheduled_at.after' => 'موعد التوصيل يجب أن يكون في المستقبل',
        ];
    }
}
