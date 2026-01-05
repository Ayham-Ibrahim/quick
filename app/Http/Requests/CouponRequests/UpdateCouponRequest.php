<?php

namespace App\Http\Requests\CouponRequests;

use App\Http\Requests\BaseFormRequest;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class UpdateCouponRequest extends BaseFormRequest
{
    /**
     * Authorization
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->is_admin;
    }
    protected function prepareForValidation(): void
    {
        $this->merge([
            'start_at' => $this->normalizeDate($this->start_at),
            'end_at'   => $this->normalizeDate($this->end_at),
        ]);
    }

    private function normalizeDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)
                ->timezone(config('app.timezone'))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return $value;
        }
    }
    /**
     * Validation Rules
     */
    public function rules(): array
    {
        return [
            'type' => 'sometimes|in:percentage,fixed',

            'amount' => [
                'sometimes',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) {
                    $type = $this->input('type', $this->route('coupon')->type ?? null);

                    if ($type === 'percentage' && $value > 100) {
                        $fail('قيمة الخصم بالنسبة لا يمكن أن تتجاوز 100%.');
                    }
                }
            ],

            'usage_limit_total' => 'sometimes|integer|min:1|max:100000',
            'usage_limit_per_user' => 'sometimes|integer|min:1|max:100',

            'start_at' => 'nullable|date|after_or_equal:today',
            'end_at' => 'nullable|date|after:start_at',

            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ];
    }

    /**
     * Attributes
     */
    public function attributes(): array
    {
        return [
            'type' => 'نوع الخصم',
            'amount' => 'قيمة الخصم',
            'usage_limit_total' => 'عدد مرات الاستخدام الكلي',
            'usage_limit_per_user' => 'عدد مرات الاستخدام لكل مستخدم',
            'start_at' => 'تاريخ بداية الكوبون',
            'end_at' => 'تاريخ نهاية الكوبون',
            'product_ids' => 'المنتجات',
        ];
    }

    /**
     * Messages
     */
    public function messages(): array
    {
        return [
            'unique' => ':attribute مستخدم مسبقاً.',
            'exists' => ':attribute غير موجود.',
            'integer' => 'حقل :attribute يجب أن يكون عدداً صحيحاً.',
            'numeric' => 'حقل :attribute يجب أن يكون رقماً.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
            'max' => 'حقل :attribute يجب ألا يتجاوز :max.',
            'in' => 'قيمة :attribute غير صحيحة.',
            'after' => 'حقل :attribute يجب أن يكون بعد :date.',
            'array' => 'حقل :attribute يجب أن يكون مصفوفة.',
            'date' => 'حقل :attribute يجب أن يكون تاريخاً صالحاً.',
            'date_format' => 'حقل :attribute يجب أن يكون بالصيغة :format.',
            'after_or_equal' => 'حقل :attribute يجب أن يكون من تاريخ اليوم أو بعده.',
        ];
    }

    /**
     * Authorization failure response
     */
    protected function failedAuthorization()
    {
        throw new HttpResponseException(
            response()->json([
                'status' => 'error',
                'message' => 'غير مصرح لك بتنفيذ هذا الإجراء.',
            ], 403)
        );
    }
}
