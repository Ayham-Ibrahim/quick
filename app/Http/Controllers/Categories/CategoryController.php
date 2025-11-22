<?php

namespace App\Http\Controllers\Categories;

use Illuminate\Http\Request;
use App\Models\Categories\Category;
use App\Http\Controllers\Controller;
use App\Models\Categories\SubCategory;
use App\Services\Category\CategoryService;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;

class CategoryController extends Controller
{
       /**
     * The service class responsible for handling Category-related business logic.
     *
     * @var \App\Services\Category\CategoryService
     */
    protected $categoryService;

    /**
     * Create a new CategoryController instance and inject the CategoryService.
     *
     * @param CategoryService $categoryService
     */
    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

     /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return $this->success(
            Category::select('id', 'name', 'image')
                ->get(),
            'Categories retrieved successfully'
        );
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        return $this->success(
            $this->categoryService->storeCategory($request->validated()),
            'Category created successfully',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        $data = $category->load('subCategories');
        return $this->success(
            $data,
            'Category retrieved successfully'
        );
    }

     /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        return $this->success(
            $this->categoryService->updateCategory($request->validated(), $category),
            'Category updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        $category->delete();
        return $this->success(
            null,
            'Category deleted successfully',
            204
        );
    }

    /**
     * get subcategories of selected category 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSubCategories(Request $request)
    {
        $request->validate([
            'category_ids' => 'required|array',
            'category_ids.*' => 'exists:categories,id'
        ]);

        $subCategories = SubCategory::whereIn('category_id', $request->category_ids)->get();

        return response()->json([
            'status' => true,
            'data' => $subCategories,
        ]);
    }
}
