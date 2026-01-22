<?php

namespace App\Http\Requests\Device;

use App\Http\Requests\BaseFormRequest;

class RegisterDeviceRequest extends BaseFormRequest
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
            'fcm_token' => ['required', 'string', 'min:10'],
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
            'fcm_token.required' => 'توكن الإشعارات مطلوب',
            'fcm_token.min' => 'توكن الإشعارات غير صالح',
        ];
    }
}
