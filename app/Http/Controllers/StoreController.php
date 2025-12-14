<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\Store\StoreService;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\StoreResource;
use App\Models\Categories\SubCategory;
use App\Http\Requests\StoreRequests\StoreStoreRequest;
use App\Http\Requests\StoreRequests\UpdateStoreRequest;
use App\Http\Requests\StoreRequests\UpdateStoreProfileRequest;

class StoreController extends Controller
{
    protected $storeService;

    public function __construct(StoreService $storeService)
    {
        $this->storeService = $storeService;
    }

    public function index()
    {
        $paginatedStores = $this->storeService->paginate();

        return $this->paginate(
            StoreResource::collection($paginatedStores)->resource, 
            "تم جلب البيانات بنجاح"
        );
    }


    public function store(StoreStoreRequest $request)
    {
        $store = $this->storeService->storeStore($request->validated());
        return $this->success(new StoreResource($store), 'تم انشاء المتجر بنجاح', 201);
    }

    public function show($id)
    {
        $store = $this->storeService->find($id);
        return $this->success(new StoreResource($store));
    }

    public function update(UpdateStoreRequest $request, Store $store)
    {
        $store = $this->storeService->updateStore($request->validated(), $store);
        return $this->success(new StoreResource($store), 'تم تحديث بيانات المتجر بنجاح');
    }
    /**
     * Update store profile data.
     */
    public function updateStoreProfile(UpdateStoreProfileRequest $request)
    {
        $store = $this->storeService->updateStoreProfile($request->validated());
        return $this->success(new StoreResource($store), 'تم تحديث بياناتك بنجاح');
    }

    /**
     * Show store profile
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile()
    {
        /** @var \App\Models\Store $store */
        $store = Auth::guard('store')->user();

        return $this->success(
            new StoreResource($store->load(['subCategories', 'categories'])),
            'بيانات المتجر'
        );
    }

    public function destroy(Store $store)
    {
        $this->storeService->deleteStore($store);
        return $this->success([], 'تم حذف المتجر بنجاح');
    }

    /**
     * Get categories associated with the authenticated store
     */
    public function getStoreCategories()
    {
        $store = Auth::guard('store')->user();

        $categories = $store->categories()->get();

        return response()->json([
            'status' => true,
            'data' => $categories
        ]);
    }

    /**
     * Get subcategories of a specific category associated with the authenticated store
     */
    public function getStoreSubCategories($category_id)
    {
        $store = Auth::guard('store')->user();

        $hasCategory = $store->categories()->where('categories.id', $category_id)->exists();

        if (!$hasCategory) {
            return response()->json([
                'status' => false,
                'message' => 'هذه الفئة غير مرتبطة بمتجرك',
            ], 403);
        }

        $subcategoriesOfCategory = SubCategory::where('category_id', $category_id)->pluck('id');

        $storeSubcategories = $store->subCategories()
            ->whereIn('sub_categories.id', $subcategoriesOfCategory)
            ->get();

        return response()->json([
            'status' => true,
            'data' => $storeSubcategories
        ]);
    }

    /*
    |----------------------------------------*--------------------------*
    | USER APP METHODS
    |----------------------------------------*--------------------------*
    */

    /**
     * List of stores with only store_name and store_logo for user app slider
     */
    public function listOfStores()
    {
        $stores = $this->storeService->listOfStores();

        return $this->success( $stores, 'تم جلب البيانات بنجاح');
    }

    /**
     *  get categories of a specific store
     */
    public function getCategoriesOfStore($store_id)
    {
        $store = Store::find($store_id);

        if (! $store) {
            return response()->json([
                'status' => false,
                'message' => 'المتجر غير موجود',
            ], 404);
        }

        $categories = $store->categories()->get();

        return response()->json([
            'status' => true,
            'data' => $categories
        ]);
    }

    /**
     * get subcategories of a specific category for a specific store
     */
    public function getSubCategoriesOfStore($store_id, $category_id)
    {
        $store = Store::find($store_id);
        if (! $store) {
            return response()->json([
                'status' => false,
                'message' => 'المتجر غير موجود',
            ], 404);
        }
        $hasCategory = $store->categories()->where('categories.id', $category_id)->exists();

        if (!$hasCategory) {
            return response()->json([
                'status' => false,
                'message' => 'هذه الفئة غير مرتبطة بمتجرك',
            ], 403);
        }

        $subcategoriesOfCategory = SubCategory::where('category_id', $category_id)->pluck('id');

        $storeSubcategories = $store->subCategories()
            ->whereIn('sub_categories.id', $subcategoriesOfCategory)
            ->get();

        return response()->json([
            'status' => true,
            'data' => $storeSubcategories
        ]);
    }

    /**
     * list store's products by store id
     */
    public function getStoreProductsBySubcategory($store_id, $subcategory_id)
    {
        $store = Store::find($store_id);
        if (! $store) {
            return response()->json([
                'status' => false,
                'message' => 'المتجر غير موجود',
            ], 404);
        }

        $hasSubCategory = $store->subCategories()->where('sub_categories.id', $subcategory_id)->exists();

        if (! $hasSubCategory) {
            return response()->json([
                'status' => false,
                'message' => 'هذه الفئة الفرعية غير مرتبطة بمتجرك',
            ], 403);
        }

        $products = $store->products()->where('sub_category_id', $subcategory_id)->get();

        return response()->json([
            'status' => true,
            'data' => $products
        ]);
    }

    /**
     * show all products of a store
     */
    public function showAllProducts($store_id)
    {
        $store = Store::find($store_id);
        if (! $store) {
            return response()->json([
                'status' => false,
                'message' => 'المتجر غير موجود',
            ], 404);
        }

        $products = $store->products()->get();

        return response()->json([
            'status' => true,
            'data' => $products
        ]);
    }
}
