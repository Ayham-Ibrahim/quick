<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class AdRequest extends BaseFormRequest
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
            'ad_image_url' => 'required|image
                                    |mimes:png,jpg,jpeg
                                    |mimetypes:image/jpeg,image/png,image/jpg
                                    |max:5000',
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
            'ad_image_url' => 'صورة الاعلان'
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
            'ad_image_url.required' => 'حقل :attribute مطلوب.',
            'ad_image_url.image' => 'حقل :attribute يجب أن يكون صورة.',
            'ad_image_url.mimes' => 'الصورة يجب أن تكون من نوع: :values.',
            'ad_image_url.max' => 'حجم :attribute يجب ألا يتجاوز :max كيلوبايت (ما يعادل 5 ميجابايت).',
            'ad_image_url.mimetypes' => 'نوع ملف الصورة غير مسموح به. الأنواع المسموحة: :values.',
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
