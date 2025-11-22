<?php

namespace App\Http\Requests\RatingRequests;

use App\Http\Requests\BaseFormRequest;
use App\Models\Rating;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class UpdateRatingRequest extends BaseFormRequest
{
    /**
     * Allow only the owner of the rating to update it.
     */
    public function authorize(): bool
    {
        $rating = Rating::find($this->route('rating'));

        if (!$rating) {
            return false;
        }

        return Auth::check() && Auth::id() === $rating->user_id;
    }

    public function rules(): array
    {
        return [
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
        ];
    }

    /**
     * Custom attributes
     */
    public function attributes(): array
    {
        return [
            'rating'  => 'التقييم',
        ];
    }

    /**
     * Custom messages
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'حقل :attribute مطلوب.',
            'rating.integer'  => 'حقل :attribute يجب أن يكون رقمًا.',
            'rating.min'      => 'قيمة :attribute يجب ألا تقل عن :min.',
            'rating.max'      => 'قيمة :attribute يجب ألا تزيد عن :max.',
        ];
    }

    /**
     * Custom error on unauthorized update attempt.
     */
    protected function failedAuthorization()
    {
        throw new HttpResponseException(response()->json([
            'status'  => 'error',
            'message' => 'غير مصرح لك بتعديل هذا التقييم.',
        ], 403));
    }
}
