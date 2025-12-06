<?php

namespace App\Http\Requests\DriverRequests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateDriverProfileRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'driver_name' => 'nullable|string|max:255',

            'phone' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('drivers', 'phone')->ignore(Auth::id()),
            ],

            'password' => 'nullable|string|min:6|confirmed',

            'driver_image' => 'nullable|image
                               |mimes:png,jpg,jpeg
                               |mimetypes:image/jpeg,image/png,image/jpg
                               |max:5000',

            'front_id_image' => 'nullable|image
                                 |mimes:png,jpg,jpeg
                                 |mimetypes:image/jpeg,image/png,image/jpg
                                 |max:5000',

            'back_id_image' => 'nullable|image
                                |mimes:png,jpg,jpeg
                                |mimetypes:image/jpeg,image/png,image/jpg
                                |max:5000',

            'city'         => 'nullable|string|max:255',
            'v_location'   => 'nullable|string|max:255',
            'h_location'   => 'nullable|string|max:255',

            'vehicle_type_id' => 'nullable|exists:vehicle_types,id',

            'subcategory_ids' => 'nullable|array',
            'subcategory_ids.*' => 'exists:sub_categories,id',

            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ];
    }

    public function attributes(): array
    {
        return [
            'driver_name'       => 'اسم السائق',
            'phone'             => 'رقم الهاتف',

            'password'               => 'كلمة المرور',
            'password_confirmation'  => 'تأكيد كلمة المرور',

            'driver_image'      => 'صورة السائق',
            'front_id_image'    => 'صورة الهوية الأمامية',
            'back_id_image'     => 'صورة الهوية الخلفية',

            'city'              => 'المدينة',
            'v_location'        => 'الإحداثيات العمودية',
            'h_location'        => 'الإحداثيات الأفقية',

            'vehicle_type_id'   => 'نوع المركبة',

            'category_ids'        => 'التصنيفات',
            'category_ids.*'      => 'التصنيف',

            'subcategory_ids'     => 'التصنيفات الفرعية',
            'subcategory_ids.*'   => 'التصنيف الفرعي',
        ];
    }

    public function messages(): array
    {
        return [
            'required'      => 'حقل :attribute مطلوب.',
            'string'        => 'حقل :attribute يجب أن يكون نصاً.',
            'max'           => 'حقل :attribute يجب ألا يتجاوز :max حرفاً.',
            'unique'        => ':attribute مسجل مسبقاً.',
            'exists'        => ':attribute غير موجود.',
            'min'           => 'حقل :attribute يجب ألا يقل عن :min أحرف.',

            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',

            'image'      => 'حقل :attribute يجب أن يكون صورة.',
            'mimes'      => 'صيغة :attribute يجب أن تكون من الأنواع التالية: :values.',
            'mimetypes'  => 'نوع ملف :attribute غير مدعوم.',

            'driver_image.max'   => 'حجم :attribute يجب ألا يتجاوز :max كيلوبايت.',
            'front_id_image.max' => 'حجم :attribute يجب ألا يتجاوز :max كيلوبايت.',
            'back_id_image.max'  => 'حجم :attribute يجب ألا يتجاوز :max كيلوبايت.',

            'category_ids.array'        => 'حقل التصنيفات يجب أن يكون مصفوفة.',
            'category_ids.*.exists'     => 'أحد التصنيفات غير موجود.',

            'subcategory_ids.array'     => 'حقل التصنيفات الفرعية يجب أن يكون مصفوفة.',
            'subcategory_ids.*.exists'  => 'أحد التصنيفات الفرعية غير موجود.',

            'subcategory_ids.invalid_relation' => 'بعض التصنيفات الفرعية لا تتبع التصنيفات المختارة.',
        ];
    }

    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'status'  => 'error',
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
                    $validator->errors()->add(
                        'subcategory_ids',
                        'بعض التصنيفات الفرعية لا تتبع التصنيفات المختارة.'
                    );
                }
            }
        });
    }
}
