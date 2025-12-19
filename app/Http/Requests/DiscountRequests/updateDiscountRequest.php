<?php

namespace App\Http\Requests\DiscountRequests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class updateDiscountRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->is_admin;
    }

    public function rules(): array
    {
        return [
            'rate' => 'nullable|integer|min:1|max:100',
            'purchasing_value' => 'nullable|numeric|min:0',
        ];
    }

    public function attributes(): array
    {
        return [
            'rate' => 'نسبة الخصم',
            'purchasing_value' => 'قيمة الشراء',
        ];
    }

    public function messages(): array
    {
        return [
            'integer' => 'حقل :attribute يجب أن يكون رقماً صحيحاً.',
            'numeric' => 'حقل :attribute يجب أن يكون رقماً.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
            'max' => 'حقل :attribute يجب ألا يزيد عن :max.',
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
