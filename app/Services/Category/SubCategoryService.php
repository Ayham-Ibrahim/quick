<?php

namespace App\Services\Category;

use App\Services\Service;
use App\Services\FileStorage;
use Illuminate\Support\Facades\Log;
use App\Models\Categories\SubCategory;
use Illuminate\Http\Exceptions\HttpResponseException;

class SubCategoryService extends Service
{

    /**
     * store new category in database
     */
    public function storeSubCategory($data)
    {
        try {
            return SubCategory::create([
                'name'           => $data['name'],
                'image'          => FileStorage::storeFile($data['image'], 'SubCategory', 'img'),
            ]);
        } catch (\Throwable $th) {
            Log::error($th);
            if ($th instanceof HttpResponseException) {
                throw $th;
            }
            $this->throwExceptionJson();
        }
    }

    /**
     *  Update an existing subcategory.
     *
     */
    public function updateSubCategory($data, SubCategory $subcategory)
    {
        try {
            $subcategory->update(array_filter([
                'name' => $data['name'] ?? null,
                'image' => FileStorage::fileExists($data['image'] ?? null, $subcategory->image, 'SubCategory', 'img')
            ]));
            return $subcategory;
        } catch (\Throwable $th) {
            Log::error($th);
            if ($th instanceof HttpResponseException) {
                throw $th;
            }
            $this->throwExceptionJson();
        }
    }
}
