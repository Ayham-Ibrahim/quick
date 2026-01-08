<?php

namespace App\Http\Requests\CouponRequests;

use App\Http\Requests\BaseFormRequest;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class StoreCouponRequest extends BaseFormRequest
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
            // المتجر مطلوب
            'store_id' => 'required|exists:stores,id',

            'type' => 'required|in:percentage,fixed',

            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) {
                    if ($this->type === 'percentage' && $value > 100) {
                        $fail('قيمة الخصم بالنسبة لا يمكن أن تتجاوز 100%.');
                    }
                }
            ],

            'usage_limit_total' => 'required|integer|min:1|max:100000',
            'usage_limit_per_user' => 'required|integer|min:1|max:100',

            'start_at' => 'nullable|date|after_or_equal:today',
            'end_at' => 'nullable|date|after:start_at',


            'product_ids' => 'nullable|array|min:1',
            'product_ids.*' => 'exists:products,id',
        ];
    }

    /**
     * التحقق من أن المنتجات تنتمي للمتجر المحدد
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $storeId = $this->input('store_id');
            $productIds = $this->input('product_ids', []);

            if (!empty($productIds) && $storeId) {
                $invalidProducts = Product::whereIn('id', $productIds)
                    ->where('store_id', '!=', $storeId)
                    ->pluck('name')
                    ->toArray();

                if (!empty($invalidProducts)) {
                    $validator->errors()->add(
                        'product_ids',
                        'المنتجات التالية لا تنتمي للمتجر المحدد: ' . implode('، ', $invalidProducts)
                    );
                }
            }
        });
    }

    /**
     * Attributes (Arabic names)
     */
    public function attributes(): array
    {
        return [
            'store_id' => 'المتجر',
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
     * Custom Messages
     */
    public function messages(): array
    {
        return [
            'required' => 'حقل :attribute مطلوب.',
            'unique' => ':attribute مستخدم مسبقاً.',
            'exists' => ':attribute غير موجود.',
            'integer' => 'حقل :attribute يجب أن يكون عدداً صحيحاً.',
            'numeric' => 'حقل :attribute يجب أن يكون رقماً.',
            'min' => 'حقل :attribute يجب ألا يقل عن :min.',
            'max' => 'حقل :attribute يجب ألا يتجاوز :max.',
            'in' => 'قيمة :attribute غير صحيحة.',
            'date' => 'حقل :attribute يجب أن يكون تاريخاً صالحاً.',
            'date_format' => 'حقل :attribute يجب أن يكون بالصيغة :format.',
            'after' => 'حقل :attribute يجب أن يكون بعد :date.',
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
