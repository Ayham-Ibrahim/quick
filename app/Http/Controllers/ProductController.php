<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Products\ProductService;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;

class ProductController extends Controller
{

    /**
     * The service class responsible for handling Product-related business logic.
     *
     * @var ProductService
     */
    protected $productService;

    /**
     * Create a new ProductController instance and inject the ProductService.
     *
     * @param ProductService $productService The service responsible for Product operations.
     */
    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::where('is_accepted', true)
            ->with(['store:id,store_name,store_logo','subCategory:id,name,category_id','subCategory.category:id,name', 'images']);

        // Filter by subcategory if passed
        if ($request->has('sub_category_id')) {
            $query->where('sub_category_id', $request->sub_category_id);
        }

        $products = $query->get();

        return $this->success($products, 'تم جلب المنتجات بنجاح', 200);
    }

    /**
     * Display a listing of the current manager's products
     *
     * @param Request $request The HTTP request containing filter parameters
     * @return \Illuminate\Http\JsonResponse
     */
    public function myProducts(Request $request)
    {
        $store = Auth::guard('store')->user();
        // if (! $store instanceof Store) {
        //     throw new \Exception('غير مصرح لك بالقيام بهذا الإجراء.');
        // }
        $store_id = $store->id;
        $query = Product::where('store_id', $store_id)
            ->with(['store:id,store_name,store_logo','subCategory:id,name,category_id','subCategory.category:id,name', 'images']);

        // Filter by subcategory if passed
        if ($request->has('sub_category_id')) {
            $query->where('sub_category_id', $request->subcategory_id);
        }

        $products = $query->get();

        return $this->success($products, 'تم جلب منتجات المتجر بنجاح', 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $store = Auth::guard('store')->user();
        // if (! $store instanceof Store) {
        //     throw new \Exception('غير مصرح لك بالقيام بهذا الإجراء.');
        // }
        $data['store_id'] = $store->id;
        return $this->success(
            $this->productService->storeProduct($data),
            'تم انشاء المنتج بنجاح',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return $this->success(
            $product->load(['store:id,store_name,store_logo', 'images', 'subCategory:id,name','ratings']),
            'تم جلب معلومات المنتج بنجاح'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        return $this->success(
            $this->productService->updateProduct($request->validated(), $product),
            'تم تحديث المنتج بنجاح'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->images()->delete();
        // $product->variants()->delete();
        $product->delete();
        return $this->success(null, 'تم حذف المنتج بنجاح', 200);
    }

    /**
     * Delete a product image
     *
     * @param ProductImage $image The product image model instance
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteImage(ProductImage $image)
    {
        $image->delete();
        return $this->success(null, 'تم حذف صورة المنتج بنجاح', 200);
    }



    /**
     * FOR ADMIN USE ONLY
     * show the request's products that need accept from admin
     * @return \Illuminate\Http\JsonResponse
     */
    public function pendingProducts()
    {
        $products = Product::where('is_accepted', false)
            ->with(['store:id,store_name', 'images'])
            ->get();

        return $this->success($products,'تم جلب طلبات المنتجات بنجاح', 200);
    }

    /**
     * FOR ADMIN USE ONLY
     * accept a product's request
     * @param Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function acceptProduct(Product $product)
    {
        $product->is_accepted = true;
        $product->save();
        return $this->success(null, 'تم قبول المنتج بنجاح', 200);
    }

    /**
     * Get products of a specific store, optionally filtered by subcategory.
     *
     * @param Request $request The HTTP request containing filter parameters
     * @param int $store_id The ID of the store whose products are to be fetched
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStoreProductsBySubcategory(Request $request, $store_id)
    {
        $query = Product::where('store_id', $store_id)
            ->where('is_accepted', true)
            ->with([
                'store:id,store_name,store_logo',
                'subCategory:id,name,category_id',
                'subCategory.category:id,name',
                'images'
            ]);

        // Filter by subcategory if provided
        if ($request->has('sub_category_id') && !empty($request->sub_category_id)) {
            $query->where('sub_category_id', $request->sub_category_id);
        }

        $products = $query->get();

        return $this->success($products, 'تم جلب منتجات المتجر بنجاح', 200);
    }




}
