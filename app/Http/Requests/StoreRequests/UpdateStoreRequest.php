<?php

namespace App\Http\Requests\StoreRequests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateStoreRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->is_admin;
    }

    public function rules(): array
    {
        return [
            'store_name'                => 'nullable|string|max:255',
            'phone' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('stores', 'phone')->ignore($this->store?->id),
            ],
            'store_owner_name'          => 'nullable|string|max:255',
            'password'                  => 'nullable|string|min:6|confirmed',
            'commercial_register_image' => 'nullable|image
                                            |mimes:png,jpg,jpeg
                                            |mimetypes:image/jpeg,image/png,image/jpg
                                            |max:5000',

            'store_logo'                => 'nullable|image
                                            |mimes:png,jpg,jpeg
                                            |mimetypes:image/jpeg,image/png,image/jpg
                                            |max:5000',

            'city'                      => 'nullable|string|max:255',
            'v_location'                => 'nullable|string|max:255',
            'h_location'                => 'nullable|string|max:255',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',

            'subcategory_ids' => 'nullable|array',
            'subcategory_ids.*' => 'exists:sub_categories,id',

        ];
    }

    public function attributes(): array
    {
        return [
            'store_name'                => 'اسم المتجر',
            'phone'                     => 'رقم المتجر',
            'store_owner_name'          => 'اسم مالك المتجر',
            'password'                  => 'كلمة المرور',
            'commercial_register_image' => 'صورة السجل التجاري',
            'store_logo'                => 'شعار المتجر',
            'city'                      => 'المدينة',
            'v_location'                => 'الإحداثيات العمودية',
            'h_location'                => 'الإحداثيات الأفقية',
            'category_ids'                 => 'التصنيفات',
            'category_ids.*'               => 'التصنيف',

            'subcategory_ids'              => 'التصنيفات الفرعية',
            'subcategory_ids.*'            => 'التصنيف الفرعي',

            'password_confirmation'     => 'تأكيد كلمة المرور',
        ];
    }

    public function messages(): array
    {
        return [
            'required'                          => 'حقل :attribute مطلوب.',
            'string'                            => 'حقل :attribute يجب أن يكون نصاً.',
            'max'                               => 'حقل :attribute يجب ألا يتجاوز :max حرفاً.',
            'unique'                            => ':attribute مسجل مسبقاً.',
            'exists'                            => ':attribute غير موجود.',
            'password.confirmed'                => 'تأكيد كلمة المرور غير متطابق.',
            'min'                               => 'حقل :attribute يجب ألا يقل عن :min أحرف.',

            'image'                             => 'حقل :attribute يجب أن يكون صورة.',
            'mimes'                             => 'صيغة :attribute يجب أن تكون من الأنواع التالية: :values.',
            'mimetypes'                         => 'نوع ملف :attribute غير مدعوم.',
            'commercial_register_image.max'     => 'حجم :attribute يجب ألا يتجاوز :max كيلوبايت (ما يعادل 5 ميجابايت).',
            'store_logo.max'                    => 'حجم :attribute يجب ألا يتجاوز :max كيلوبايت (ما يعادل 5 ميجابايت).',

            'category_ids.array'                => 'حقل التصنيفات يجب أن يكون مصفوفة.',
            'category_ids.*.exists'             => 'أحد التصنيفات غير موجود.',

            'subcategory_ids.array'             => 'حقل التصنيفات الفرعية يجب أن يكون مصفوفة.',
            'subcategory_ids.*.exists'          => 'أحد التصنيفات الفرعية غير موجود.',

            'subcategory_ids.invalid_relation'  => 'بعض التصنيفات الفرعية لا تتبع التصنيفات المختارة.',
        ];
    }

    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'غير مصرح لك بالقيام بهذا الإجراء.'
        ], 403));
    }
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->subcategory_ids && $this->category_ids) {
                $invalid = DB::table('sub_categories')
                    ->whereIn('id', $this->subcategory_ids)
                    ->whereNotIn('category_id', $this->category_ids)
                    ->exists();

                if ($invalid) {
                    $validator->errors()->add('subcategory_ids', 'بعض التصنيفات الفرعية لا تتبع التصنيفات المختارة.');
                }
            }
        });
    }
}
