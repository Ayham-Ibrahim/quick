<?php

namespace App\Http\Requests\CouponRequests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class StoreCouponRequest extends BaseFormRequest
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
            'type' => 'required|in:percentage,fixed',
            'discount_amount' => [
                'required',
                'numeric',
                'min:0.1',
                function ($value, $fail) {
                    if ($this->type === 'percentage' && $value > 100) {
                        $fail('قيمة الخصم بالنسبة لا يمكن أن تتجاوز 100%.');
                    }
                }
            ],
            'expiration_duration' => 'required|integer|min:1|max:365',
            'usage_limit' => 'required|integer|min:1|max:9999',
            'product_id' => 'required|exists:products,id',
            'start_at' => 'nullable|date|after_or_equal:today'
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
            'type' => 'نوع الخصم',
            'discount_amount' => 'قيمة الخصم',
            'expiration_duration' => 'مدة الصلاحية',
            'usage_limit' => 'عدد مرات الاستخدام',
            'product_id' => 'رقم معرف المنتج',
            'start_at' => 'تاريخ بدء الكوبون'
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
            'required'   => 'حقل :attribute مطلوب.',
            'string'     => 'حقل :attribute يجب أن يكون نصاً.',
            'max'        => 'حقل :attribute يجب ألا يتجاوز :max.',
            'exists'     => ':attribute غير موجود.',
            'min'        => 'حقل :attribute يجب ألا يقل عن :min.',
            'decimal'    => 'حقل :attribute يجب أن يكون عدداً عشرياً.',
            'integer'    => 'حقل :attribute يجب أن يكون عدداً صحيحاً.',
            'in'         => 'حقل :attribute يجب أن يكون: percentage أو fixed',
            'after_or_equal' => 'تاريخ بدء الكوبون يجب أن يكون من تاريخ اليوم أو بعده.'
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
