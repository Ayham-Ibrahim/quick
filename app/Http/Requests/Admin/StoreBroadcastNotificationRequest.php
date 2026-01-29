<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\Notification;

class StoreBroadcastNotificationRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Add admin authorization logic if needed
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $validTargetTypes = array_keys(Notification::getTargetTypes());

        return [
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:1000',
            'target_types' => 'required|array|min:1',
            'target_types.*' => 'required|string|in:' . implode(',', $validTargetTypes),
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
            'title.required' => 'عنوان الإشعار مطلوب',
            'title.max' => 'عنوان الإشعار يجب ألا يتجاوز 255 حرف',
            'content.required' => 'محتوى الإشعار مطلوب',
            'content.max' => 'محتوى الإشعار يجب ألا يتجاوز 1000 حرف',
            'target_types.required' => 'يجب اختيار فئة مستهدفة واحدة على الأقل',
            'target_types.array' => 'الفئات المستهدفة يجب أن تكون مصفوفة',
            'target_types.min' => 'يجب اختيار فئة مستهدفة واحدة على الأقل',
            'target_types.*.in' => 'نوع الفئة المستهدفة غير صالح',
        ];
    }
}
