<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequests\StoreStoreRequest;
use App\Http\Requests\StoreRequests\UpdateStoreProfileRequest;
use App\Http\Requests\StoreRequests\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Services\Store\StoreService;
use Illuminate\Support\Facades\Auth;

class StoreController extends Controller
{
    protected $storeService;

    public function __construct(StoreService $storeService)
    {
        $this->storeService = $storeService;
    }

    public function index()
    {
        return $this->success($this->storeService->paginate());
    }


    public function store(StoreStoreRequest $request)
    {
        $store = $this->storeService->storeStore($request->validated());
        return $this->success(new StoreResource($store), 'Store created successfully', 201);
    }

    public function show($id)
    {
        $store = $this->storeService->find($id);
        return $this->success(new StoreResource($store));
    }

    public function update(UpdateStoreRequest $request, Store $store)
    {
        $store = $this->storeService->updateStore($request->validated(), $store);
        return $this->success(new StoreResource($store), 'Store updated successfully');
    }
    /**
     * Update store data.
     */
    public function updateStoreProfile(UpdateStoreProfileRequest $request)
    {
        $updated = $this->storeService->updateStoreProfile($request->validated());
        return $this->success($updated, 'تم تحديث بياناتك بنجاح');
    }

    public function profile()
    {
        return $this->success(
            new StoreResource(Auth::user()->load(['subCategories', 'categories'])),
            'بيانات المتجر'
        );
    }

    public function destroy(Store $store)
    {
        $this->storeService->deleteStore($store);
        return $this->success([], 'Store deleted successfully');
    }
}
