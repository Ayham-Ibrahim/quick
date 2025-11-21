<?php

namespace App\Http\Controllers;

use App\Http\Requests\RatingRequests\StoreRatingRequest;
use App\Http\Requests\RatingRequests\UpdateRatingRequest;
use App\Services\RatingService;

class RatingController extends Controller
{
    protected RatingService $service;

    public function __construct(RatingService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /ratings
     */
    public function index()
    {
        $data = $this->service->index();
        return $this->success($data, "Data retrieved successfully");
    }

    /**
     * POST /ratings
     */
    public function store(StoreRatingRequest $request)
    {
        $data = $this->service->store($request->validated());
        return $this->success($data, "Rating created successfully", 201);
    }

    /**
     * GET /ratings/{id}
     */
    public function show($id)
    {
        $data = $this->service->show($id);
        return $this->success($data, "Rating retrieved successfully");
    }

    /**
     * PUT /ratings/{id}
     */
    public function update(UpdateRatingRequest $request, $id)
    {
        $data = $this->service->update($id, $request->validated());
        return $this->success($data, "Rating updated successfully");
    }

    /**
     * DELETE /ratings/{id}
     */
    public function destroy($id)
    {
        $this->service->delete($id);
        return $this->success(null, "Rating deleted successfully");
    }
}
