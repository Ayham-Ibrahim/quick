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
            SubCategory::select('id', 'name', 'image')
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
    public function show(SubCategory $subCategory)
    {
        $data = $subCategory->load('subCategories');
        return $this->success(
            $data,
            'SubCategory retrieved successfully'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSubCategoryRequest $request, SubCategory $subCategory)
    {
        return $this->success(
            $this->subCategoryService->updateSubCategory($request->validated(), $subCategory),
            'SubCategory updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SubCategory $subCategory)
    {
        $subCategory->delete();
        return $this->success(
            null,
            'SubCategory deleted successfully',
            204
        );
    }
}
