<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\BaseFormRequest;

class ReorderRequest extends BaseFormRequest
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
            'delivery_address' => 'nullable|string|max:500',
            'delivery_fee' => 'nullable|numeric|min:0',
            'is_immediate_delivery' => 'nullable|boolean',
            'requested_delivery_at' => 'nullable|date|after:now',
            'notes' => 'nullable|string|max:1000',
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
            'delivery_address.max' => 'عنوان التوصيل يجب ألا يتجاوز 500 حرف',
            'delivery_fee.numeric' => 'رسوم التوصيل يجب أن تكون رقماً',
            'delivery_fee.min' => 'رسوم التوصيل يجب أن تكون 0 أو أكثر',
            'is_immediate_delivery.boolean' => 'حقل التوصيل الفوري يجب أن يكون صحيح أو خطأ',
            'requested_delivery_at.date' => 'تاريخ التوصيل المطلوب غير صالح',
            'requested_delivery_at.after' => 'تاريخ التوصيل المطلوب يجب أن يكون في المستقبل',
            'notes.max' => 'الملاحظات يجب ألا تتجاوز 1000 حرف',
        ];
    }
}
