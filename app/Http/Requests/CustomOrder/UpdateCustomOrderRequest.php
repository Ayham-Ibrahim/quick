<?php

namespace App\Http\Requests\CustomOrder;

use App\Http\Requests\BaseFormRequest;

class UpdateCustomOrderRequest extends BaseFormRequest
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
            'delivery_address' => 'sometimes|string|max:500',
            'delivery_lat' => 'nullable|numeric|between:-90,90',
            'delivery_lng' => 'nullable|numeric|between:-180,180',
            'distance_km' => 'sometimes|numeric|min:0.1|max:100',

            // موعد التوصيل
            'is_immediate' => 'sometimes|boolean',
            'scheduled_at' => 'nullable|date|after:now',

            // العناصر (إذا أُرسلت، سيتم استبدال القديمة بالكامل)
            'items' => 'sometimes|array|min:1|max:10',
            'items.*.description' => 'required_with:items|string|max:1000',
            'items.*.pickup_address' => 'required_with:items|string|max:500',
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
            'distance_km.min' => 'المسافة يجب أن تكون أكبر من 0.1 كم',
            'items.min' => 'يجب إضافة طلب واحد على الأقل',
            'items.max' => 'الحد الأقصى 10 طلبات',
            'items.*.description.required_with' => 'وصف الطلب مطلوب',
            'items.*.pickup_address.required_with' => 'موقع الاستلام مطلوب',
            'scheduled_at.after' => 'موعد التوصيل يجب أن يكون في المستقبل',
        ];
    }
}
