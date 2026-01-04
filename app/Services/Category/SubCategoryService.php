<?php

namespace App\Services\Category;

use App\Services\Service;
use App\Services\FileStorage;
use Illuminate\Support\Facades\DB;
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
            DB::beginTransaction();

            $subCategory = SubCategory::create([
                'name'                          => $data['name'],
                'category_id'                   => $data['category_id'],
                'image'                         => FileStorage::storeFile($data['image'], 'SubCategory', 'img'),
                'price_depends_on_attributes'   => $data['price_depends_on_attributes'] ?? false,
                'quantity_depends_on_attributes' => $data['quantity_depends_on_attributes'] ?? false,
            ]);

            // Attach attributes if provided
            if (!empty($data['attributes'])) {
                $this->syncAttributes($subCategory, $data['attributes']);
            }

            DB::commit();

            return $subCategory->load(['attributes', 'category:id,name']);
        } catch (\Throwable $th) {
            DB::rollBack();
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
            DB::beginTransaction();

            $subcategory->update(array_filter([
                'name' => $data['name'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'image' => FileStorage::fileExists($data['image'] ?? null, $subcategory->image, 'SubCategory', 'img'),
                'price_depends_on_attributes' => $data['price_depends_on_attributes'] ?? $subcategory->price_depends_on_attributes,
                'quantity_depends_on_attributes' => $data['quantity_depends_on_attributes'] ?? $subcategory->quantity_depends_on_attributes,
            ], fn($value) => $value !== null));

            // Update attributes if provided
            if (isset($data['attributes'])) {
                $this->syncAttributes($subcategory, $data['attributes']);
            }

            DB::commit();

            return $subcategory->load(['attributes', 'category:id,name']);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th);
            if ($th instanceof HttpResponseException) {
                throw $th;
            }
            $this->throwExceptionJson();
        }
    }

    /**
     * Sync attributes for a subcategory
     *
     * @param SubCategory $subCategory
     * @param array $attributes Array of attribute IDs
     */
    protected function syncAttributes(SubCategory $subCategory, array $attributes): void
    {
        // Extract attribute IDs (support both formats: [1, 2, 3] or [['attribute_id' => 1], ...])
        $attributeIds = collect($attributes)->map(function ($attr) {
            return is_array($attr) ? $attr['attribute_id'] : $attr;
        })->toArray();

        $subCategory->attributes()->sync($attributeIds);
    }

    /**
     * Get subcategory with attributes and their values
     *
     * @param int $subCategoryId
     * @return SubCategory|null
     */
    public function getSubCategoryWithAttributes(int $subCategoryId): ?SubCategory
    {
        return SubCategory::with([
            'category:id,name',
            'attributes' => function ($query) {
                $query->with('values:id,attribute_id,value');
            }
        ])->find($subCategoryId);
    }

    /**
     * Get only the linked attributes for a subcategory (for product form)
     *
     * @param int $subCategoryId
     * @return array
     */
    public function getAttributesForProductForm(int $subCategoryId): array
    {
        $subCategory = SubCategory::with([
            'attributes' => function ($query) {
                $query->with(['values' => function ($q) {
                    $q->where('is_active', true)->select('id', 'attribute_id', 'value');
                }])->where('is_active', true);
            }
        ])->find($subCategoryId);

        if (!$subCategory) {
            return [];
        }

        return [
            'sub_category_id' => $subCategory->id,
            'sub_category_name' => $subCategory->name,
            'price_depends_on_attributes' => $subCategory->price_depends_on_attributes,
            'quantity_depends_on_attributes' => $subCategory->quantity_depends_on_attributes,
            'requires_variants' => $subCategory->requiresVariants(),
            'attributes' => $subCategory->attributes->map(function ($attr) {
                return [
                    'id' => $attr->id,
                    'name' => $attr->name,
                    'values' => $attr->values->map(function ($val) {
                        return [
                            'id' => $val->id,
                            'value' => $val->value,
                        ];
                    }),
                ];
            }),
        ];
    }
}
