<?php

namespace App\Http\Controllers\Categories;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Categories\SubCategory;
use App\Services\Category\SubCategoryService;
use App\Http\Requests\Category\StoreSubCategoryRequest;
use App\Http\Requests\Category\UpdateSubCategoryRequest;

class SubCategoryController extends Controller
{
    /**
     * The service class responsible for handling SubCategory-related business logic.
     *
     * @var SubCategoryService
     */
    protected $subCategoryService;

    /**
     * Create a new SubCategoryController instance and inject the SubCategoryService.
     *
     * @param SubCategoryService $subCategoryService
     */
    public function __construct(SubCategoryService $subCategoryService)
    {
        $this->subCategoryService = $subCategoryService;
    }


    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return $this->success(
            SubCategory::with(['category:id,name', 'attributes:id,name'])
                    ->select('id', 'name', 'image', 'category_id', 'price_depends_on_attributes', 'quantity_depends_on_attributes')
                    ->get(),
            'تم جلب الاقسام الفرعية بنجاح'
        );
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSubCategoryRequest $request)
    {
        return $this->success(
            $this->subCategoryService->storeSubCategory($request->validated()),
            'تم انشاء القسم الفرعي بنجاح',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $subCategory = $this->subCategoryService->getSubCategoryWithAttributes($id);

        if (!$subCategory) {
            return response()->json([
                'status' => 'error',
                'message' => 'الفئة الفرعية غير موجودة',
                'data' => null
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'تم جلب معلومات الفئة الفرعية بنجاح',
            'data' => $subCategory
        ]);
    }

    /**
     * Get attributes linked to a subcategory (for product form)
     */
    public function getAttributesForProduct($subCategoryId)
    {
        $data = $this->subCategoryService->getAttributesForProductForm($subCategoryId);

        if (empty($data)) {
            return response()->json([
                'status' => 'error',
                'message' => 'الفئة الفرعية غير موجودة',
                'data' => null
            ], 404);
        }

        return $this->success($data, 'تم جلب خصائص الفئة الفرعية بنجاح');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSubCategoryRequest $request, $id)
    {
        $subCategory = SubCategory::with('category:id,name')->find($id);

        if (!$subCategory) {
            return response()->json([
                'status' => 'error',
                'message' => 'الفئة الفرعية غير موجودة',
                'data' => null
            ], 404);
        }

        return $this->success(
            $this->subCategoryService->updateSubCategory($request->validated(), $subCategory),
            'تم تحديث القسم الفرعي بنجاح'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $subCategory = SubCategory::find($id);

        if (!$subCategory) {
            return response()->json([
                'status' => 'error',
                'message' => 'الفئة الفرعية غير موجودة',
                'data' => null
            ], 404);
        }

        $subCategory->delete();
        return $this->success(
            null,
            'تم حذف القسم الفرعي بنجاح',
            200
        );
    }
  
}
