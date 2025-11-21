<?php

namespace App\Services;

use App\Models\Rating;
use App\Http\Resources\RatingResource;
use Illuminate\Support\Facades\Auth;

class RatingService extends Service
{
    /**
     * Get paginated ratings
     */
    public function index()
    {
        $ratings = Rating::with('rateable')->paginate(10);

        return RatingResource::collection($ratings);
    }

    /**
     * Store new rating
     */
    public function store(array $data)
{
    // User can create only one rating per item
    $existing = Rating::where('user_id', Auth::id())
        ->where('rateable_type', $data['rateable_type'])
        ->where('rateable_id', $data['rateable_id'])
        ->first();

    if ($existing) {
        $this->throwExceptionJson("لقد قمت بتقييم هذا العنصر مسبقاً.", 409);
    }

    $rating = Rating::create([
        'rating'         => $data['rating'],
        'user_id'        => Auth::id(),
        'rateable_id'    => $data['rateable_id'],
        'rateable_type'  => $data['rateable_type'],
    ]);

    return new RatingResource($rating);
}


    /**
     * Show a single rating
     */
    public function show($id)
    {
        $rating = Rating::with('rateable')->find($id);

        if (!$rating) {
            $this->throwExceptionJson("التقييم غير موجود", 404);
        }

        return new RatingResource($rating);
    }

    /**
     * Update an existing rating
     */
    public function update($id, array $data)
    {
        $rating = Rating::find($id);

        if (!$rating) {
            $this->throwExceptionJson("التقييم غير موجود", 404);
        }

        if ($rating->user_id !== Auth::id()) {
            $this->throwExceptionJson("غير مصرح لك بتعديل هذا التقييم", 403);
        }

        $rating->update([
            'rating'  => $data['rating'],
        ]);

        return new RatingResource($rating);
    }

    /**
     * Delete rating
     */
    public function delete($id)
    {
        $rating = Rating::find($id);

        if (!$rating) {
            $this->throwExceptionJson("التقييم غير موجود", 404);
        }

        if ($rating->user_id !== Auth::id()) {
            $this->throwExceptionJson("غير مصرح لك بحذف هذا التقييم", 403);
        }

        $rating->delete();

        return true;
    }
}

