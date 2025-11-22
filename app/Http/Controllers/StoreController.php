<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequests\StoreStoreRequest;
use App\Http\Requests\StoreRequests\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use App\Services\Store\StoreService;

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

    public function destroy(Store $store)
    {
        $this->storeService->deleteStore($store);
        return $this->success([], 'Store deleted successfully');
    }
}
