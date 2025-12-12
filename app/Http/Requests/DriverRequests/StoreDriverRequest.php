<?php

namespace App\Http\Requests\DriverRequests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class StoreDriverRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->is_admin;
    }

    public function rules(): array
    {
        return [
            'driver_name'     => 'required|string|max:255',
            'phone'           => 'required|string|max:20|unique:drivers,phone',
            'password'        => 'required|string|min:6|confirmed',

            'driver_image'    => 'required|image
                                   |mimes:png,jpg,jpeg
                                   |mimetypes:image/jpeg,image/png,image/jpg
                                   |max:5000',

            'front_id_image'  => 'required|image
                                   |mimes:png,jpg,jpeg
                                   |mimetypes:image/jpeg,image/png,image/jpg
                                   |max:5000',

            'back_id_image'   => 'required|image
                                   |mimes:png,jpg,jpeg
                                   |mimetypes:image/jpeg,image/png,image/jpg
                                   |max:5000',

            'city'            => 'nullable|string|max:255',

            'v_location'      => 'required|string|max:255',
            'h_location'      => 'required|string|max:255',

            'vehicle_type_id' => 'required|exists:vehicle_types,id',
        ];
    }

    public function attributes(): array
    {
        return [
            'driver_name'     => 'اسم السائق',
            'phone'           => 'رقم الهاتف',
            'password'        => 'كلمة المرور',
            'password_confirmation' => 'تأكيد كلمة المرور',

            'driver_image'    => 'صورة السائق',
            'front_id_image'  => 'صورة الهوية الأمامية',
            'back_id_image'   => 'صورة الهوية الخلفية',

            'city'            => 'المدينة',
            'v_location'      => 'الإحداثيات العمودية',
            'h_location'      => 'الإحداثيات الأفقية',

            'vehicle_type_id' => 'نوع المركبة',
        ];
    }

    public function messages(): array
    {
        return [
            'required'   => 'حقل :attribute مطلوب.',
            'string'     => 'حقل :attribute يجب أن يكون نصاً.',
            'max'        => 'حقل :attribute يجب ألا يتجاوز :max حرفاً.',
            'unique'     => ':attribute مسجل مسبقاً.',
            'exists'     => ':attribute غير موجود.',
            'min'        => 'حقل :attribute يجب ألا يقل عن :min أحرف.',

            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',

            'image'      => 'حقل :attribute يجب أن يكون صورة.',
            'mimes'      => 'صيغة :attribute يجب أن تكون إحدى: :values.',
            'mimetypes'  => 'نوع ملف :attribute غير مدعوم.',
            'numeric'    => 'حقل :attribute يجب أن يكون رقمياً.',

            'driver_image.max'   => 'حجم :attribute يجب ألا يتجاوز :max كيلوبايت.',
            'front_id_image.max' => 'حجم :attribute يجب ألا يتجاوز :max كيلوبايت.',
            'back_id_image.max'  => 'حجم :attribute يجب ألا يتجاوز :max كيلوبايت.',
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
