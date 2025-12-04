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
            SubCategory::with('category:id,name') 
                    ->select('id', 'name', 'image', 'category_id')
                    ->get(),
            'SubCategories retrieved successfully'
        );
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSubCategoryRequest $request)
    {
        return $this->success(
            $this->subCategoryService->storeSubCategory($request->validated()),
            'SubCategory created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $subCategory = SubCategory::with('category:id,name')->find($id);

        if (!$subCategory) {
            return response()->json([
                'status' => 'error',
                'message' => 'SubCategory not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'SubCategory retrieved successfully',
            'data' => $subCategory
        ]);
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
                'message' => 'SubCategory not found',
                'data' => null
            ], 404);
        }

        return $this->success(
            $this->subCategoryService->updateSubCategory($request->validated(), $subCategory),
            'SubCategory updated successfully'
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
                'message' => 'SubCategory not found',
                'data' => null
            ], 404);
        }

        $subCategory-delete();
        return $this->success(
            null,
            'SubCategory deleted successfully',
            200
        );
    }
  
}
