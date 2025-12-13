<?php

namespace App\Http\Requests\RatingRequests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRatingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],

            // 'rateable_type' => ['required', 'string', 'in:store,product'],
            'rateable_type' => ['required', 'string', Rule::in([\App\Models\Store::class,
             \App\Models\Product::class,\App\Models\Driver::class
             ])],
            'rateable_id'   => ['required', 'integer'],
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->rateable_type === 'store') {
            $this->merge(['rateable_type' => \App\Models\Store::class]);
        }

        if ($this->rateable_type === 'product') {
            $this->merge(['rateable_type' => \App\Models\Product::class]);
        }

        if ($this->rateable_type === 'driver') {
            $this->merge(['rateable_type' => \App\Models\Driver::class]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rating'         => 'التقييم',
            'rateable_type'  => 'نوع العنصر',
            'rateable_id'    => 'معرّف العنصر',
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
            'rating.required' => 'حقل :attribute مطلوب.',
            'rating.integer'  => 'حقل :attribute يجب أن يكون رقمًا.',
            'rating.min'      => 'قيمة :attribute يجب ألا تقل عن :min.',
            'rating.max'      => 'قيمة :attribute يجب ألا تزيد عن :max.',

            'rateable_type.required' => 'حقل :attribute مطلوب.',
            'rateable_type.in'       => 'قيمة :attribute غير صالحة.',

            'rateable_id.required' => 'حقل :attribute مطلوب.',
            'rateable_id.integer'  => 'حقل :attribute يجب أن يكون رقمًا.',
        ];
    }
}
