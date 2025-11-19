<?php

namespace App\Services\Category;

use App\Services\Service;
use App\Services\FileStorage;
use App\Models\Categories\Category;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Exceptions\HttpResponseException;

class CategoryService extends Service
{

    /**
     * store new category in database
     */
    public function storeCategory($data)
    {
        try {
            return Category::create([
                'name'           => $data['name'],
                'image'          => FileStorage::storeFile($data['image'], 'Category', 'img'),
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
     *  Update an existing category.
     *
     */
    public function updateCategory($data, Category $category)
    {
        try {
            $category->update(array_filter([
                'name' => $data['name'] ?? null,
                'image' => FileStorage::fileExists($data['image'] ?? null, $category->image, 'Category', 'img')
            ]));
            return $category;
        } catch (\Throwable $th) {
            Log::error($th);
            if ($th instanceof HttpResponseException) {
                throw $th;
            }
            $this->throwExceptionJson();
        }
    }
}
